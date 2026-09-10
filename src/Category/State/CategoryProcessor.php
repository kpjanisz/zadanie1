<?php

declare(strict_types=1);

namespace App\Category\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Category\Contract\CategoryUsageCheckerInterface;
use App\Category\Dto\CategoryOutput;
use App\Category\Dto\CreateCategoryInput;
use App\Category\Dto\UpdateCategoryInput;
use App\Category\Entity\Category;
use App\Category\Exception\CategoryCodeAlreadyExistsException;
use App\Category\Exception\CategoryInUseException;
use App\Category\Exception\CategoryNotFoundException;
use App\Category\Http\CategoryEtag;
use App\Category\Mapper\CategoryMapper;
use App\Category\Repository\CategoryRepository;
use App\Shared\Http\PreconditionChecker;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles Post, Patch and Delete for categories.
 *
 * @implements ProcessorInterface<CreateCategoryInput|UpdateCategoryInput|CategoryOutput, CategoryOutput|null>
 */
final readonly class CategoryProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private CategoryUsageCheckerInterface $usageChecker,
        private CategoryMapper $mapper,
        private PreconditionChecker $preconditions,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CategoryOutput
    {
        if ($operation instanceof DeleteOperationInterface) {
            $category = $this->requireCategory($uriVariables);
            $this->preconditions->assertMatches(CategoryEtag::for($category));
            $this->delete($category);

            return null;
        }

        if ($data instanceof CreateCategoryInput) {
            return $this->create($data);
        }

        if ($data instanceof UpdateCategoryInput) {
            $category = $this->requireCategory($uriVariables);
            $this->preconditions->assertMatches(CategoryEtag::for($category));

            return $this->update($category, $data);
        }

        throw new \LogicException(\sprintf('Unsupported input "%s" for operation "%s".', get_debug_type($data), $operation->getName() ?? ''));
    }

    private function create(CreateCategoryInput $input): CategoryOutput
    {
        $code = trim((string) $input->code);
        $this->assertCodeIsFree($code, null);

        $category = new Category($code);
        $this->entityManager->persist($category);
        $this->flush($code);
        $this->preconditions->remember(CategoryEtag::for($category));

        return $this->mapper->mapToOutput($category);
    }

    private function update(Category $category, UpdateCategoryInput $input): CategoryOutput
    {
        // null means "field not supplied"; only a supplied value is applied.
        if (null !== $input->code) {
            $code = trim($input->code);
            $this->assertCodeIsFree($code, $category->getId());
            $category->setCode($code);
        }

        $this->flush($category->getCode());
        $this->preconditions->remember(CategoryEtag::for($category));

        return $this->mapper->mapToOutput($category);
    }

    private function delete(Category $category): void
    {
        $id = $category->getId() ?? throw new \LogicException('Cannot delete an unpersisted category.');

        // Products require at least one category, so a category still in use must
        // not disappear from under them.
        $productCount = $this->usageChecker->countUsages($id);
        if ($productCount > 0) {
            throw new CategoryInUseException($id, $productCount);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }

    private function assertCodeIsFree(string $code, ?int $exceptId): void
    {
        if ($this->categoryRepository->codeExists($code, $exceptId)) {
            throw new CategoryCodeAlreadyExistsException($code);
        }
    }

    /**
     * The check above can still lose a race with a concurrent insert; the unique
     * index is the real guarantee, and this turns its violation into the same
     * domain exception instead of a 500.
     */
    private function flush(string $code): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new CategoryCodeAlreadyExistsException($code, $e);
        }
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function requireCategory(array $uriVariables): Category
    {
        $id = $uriVariables['id'] ?? null;

        if (!is_numeric($id)) {
            throw new CategoryNotFoundException(\is_scalar($id) ? (string) $id : '');
        }

        return $this->categoryRepository->find((int) $id)
            ?? throw new CategoryNotFoundException((int) $id);
    }
}
