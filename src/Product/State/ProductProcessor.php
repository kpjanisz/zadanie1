<?php

declare(strict_types=1);

namespace App\Product\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Notification\Enum\OperationAction;
use App\Notification\Service\OperationLogRecorder;
use App\Product\Dto\CreateProductInput;
use App\Product\Dto\ProductChangedNotification;
use App\Product\Dto\ProductOutput;
use App\Product\Dto\UpdateProductInput;
use App\Product\Entity\Product;
use App\Product\Event\ProductChangedEvent;
use App\Product\Exception\ProductNotFoundException;
use App\Product\Http\ProductEtag;
use App\Product\Mapper\ProductMapper;
use App\Product\Repository\ProductRepository;
use App\Product\Service\CategoryLinker;
use App\Product\Service\PriceNormalizer;
use App\Shared\Http\PreconditionChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Handles Post, Patch and Delete for products.
 *
 * Stays thin: resolving categories lives in CategoryLinker, and everything that
 * happens after a save (audit log, e-mail, future Slack/SMS) is reached through
 * one event, so this class never grows a branch per notification channel.
 *
 * @implements ProcessorInterface<CreateProductInput|UpdateProductInput|ProductOutput, ProductOutput|null>
 */
final readonly class ProductProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $productRepository,
        private CategoryLinker $categoryLinker,
        private PriceNormalizer $priceNormalizer,
        private OperationLogRecorder $auditRecorder,
        private ProductMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private PreconditionChecker $preconditions,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProductOutput
    {
        if ($operation instanceof DeleteOperationInterface) {
            $product = $this->requireProduct($uriVariables);
            $this->preconditions->assertMatches(ProductEtag::for($product));
            $this->delete($product);

            return null;
        }

        if ($data instanceof CreateProductInput) {
            return $this->create($data);
        }

        if ($data instanceof UpdateProductInput) {
            $product = $this->requireProduct($uriVariables);
            $this->preconditions->assertMatches(ProductEtag::for($product));

            return $this->update($product, $data);
        }

        throw new \LogicException(\sprintf('Unsupported input "%s" for operation "%s".', get_debug_type($data), $operation->getName() ?? ''));
    }

    private function create(CreateProductInput $input): ProductOutput
    {
        $product = new Product(
            name: trim((string) $input->name),
            price: $this->priceNormalizer->normalise((string) $input->price),
        );

        foreach ($this->categoryLinker->resolve($input->categoryIds) as $category) {
            $product->addCategory($category);
        }

        $notification = $this->saveAtomically($product, OperationAction::Created, function () use ($product): void {
            $this->entityManager->persist($product);
        });

        $this->announce($notification);
        $this->preconditions->remember(ProductEtag::for($product));

        return $this->mapper->mapToOutput($product);
    }

    private function update(Product $product, UpdateProductInput $input): ProductOutput
    {
        $changed = false;

        if (null !== $input->name) {
            $product->setName(trim($input->name));
            $changed = true;
        }

        if (null !== $input->price) {
            $product->setPrice($this->priceNormalizer->normalise((string) $input->price));
            $changed = true;
        }

        if (null !== $input->categoryIds) {
            $categories = $this->categoryLinker->resolve($input->categoryIds);

            $product->clearCategories();
            foreach ($categories as $category) {
                $product->addCategory($category);
            }

            $changed = true;
        }

        // Doctrine does not treat a changed ManyToMany collection as a change to
        // the owning entity, so #[ORM\PreUpdate] would not fire for a
        // categories-only edit. Touching the field both fixes updatedAt and is
        // what makes the UnitOfWork schedule the UPDATE at all.
        if ($changed) {
            $product->markUpdated();
        }

        if (!$changed) {
            return $this->mapper->mapToOutput($product);
        }

        $notification = $this->saveAtomically($product, OperationAction::Updated, static function (): void {});

        $this->announce($notification);
        $this->preconditions->remember(ProductEtag::for($product));

        return $this->mapper->mapToOutput($product);
    }

    /**
     * A deletion is the most interesting entry an audit trail can hold, so it is
     * recorded — in the same transaction as the removal, like every other write.
     *
     * No notification is dispatched: the task scopes those to saving a product,
     * and an e-mail about a product that no longer exists would be noise. The
     * trail keeps the record either way.
     */
    private function delete(Product $product): void
    {
        // Built before the removal, while the entity still carries its id.
        $notification = ProductChangedNotification::fromProduct($product, OperationAction::Deleted);

        $this->entityManager->wrapInTransaction(function () use ($product, $notification): void {
            $this->auditRecorder->record($notification);
            $this->entityManager->remove($product);
        });
    }

    /**
     * Persists the product and its audit row in ONE transaction.
     *
     * The audit trail is domain data, not a side effect: writing it from a
     * notification channel would put it in a second transaction, and a failure
     * there would leave a product with no record of how it came to exist. The
     * inner flush is what assigns the id the notification needs.
     */
    private function saveAtomically(Product $product, OperationAction $action, callable $prepare): ProductChangedNotification
    {
        return $this->entityManager->wrapInTransaction(function () use ($product, $action, $prepare): ProductChangedNotification {
            $prepare();
            $this->entityManager->flush();

            $notification = ProductChangedNotification::fromProduct($product, $action);
            $this->auditRecorder->record($notification);

            return $notification;
        });
    }

    /**
     * Runs only after the transaction has committed, so no channel can send an
     * e-mail about a product that was rolled back — and no network call is made
     * while database locks are held.
     */
    private function announce(ProductChangedNotification $notification): void
    {
        $this->eventDispatcher->dispatch(new ProductChangedEvent($notification));
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function requireProduct(array $uriVariables): Product
    {
        $id = $uriVariables['id'] ?? null;

        if (!is_numeric($id)) {
            throw new ProductNotFoundException(\is_scalar($id) ? (string) $id : '');
        }

        // find(), not findOneWithCategories(): the provider declared on this
        // operation has already loaded the product WITH its categories, so the
        // entity is in Doctrine's identity map and find() returns it without
        // touching the database. A DQL query would bypass the map and repeat the
        // very same SELECT — measured as a duplicate JOIN on every PATCH/DELETE.
        return $this->productRepository->find((int) $id)
            ?? throw new ProductNotFoundException((int) $id);
    }
}
