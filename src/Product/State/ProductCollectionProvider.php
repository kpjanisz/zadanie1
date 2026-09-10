<?php

declare(strict_types=1);

namespace App\Product\State;

use ApiPlatform\State\ProviderInterface;
use App\Product\Dto\ProductOutput;
use App\Product\Entity\Product;
use App\Product\Http\ProductEtag;
use App\Product\Mapper\ProductMapper;
use App\Shared\Http\CanonicalQuery;
use App\Shared\Http\PreconditionChecker;
use App\Shared\State\MappedCollectionProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @extends MappedCollectionProvider<Product, ProductOutput>
 */
final readonly class ProductCollectionProvider extends MappedCollectionProvider
{
    /**
     * @param ProviderInterface<Product> $doctrineCollectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        ProviderInterface $doctrineCollectionProvider,
        private ProductMapper $mapper,
        private PreconditionChecker $preconditions,
        private RequestStack $requestStack,
    ) {
        parent::__construct($doctrineCollectionProvider);
    }

    protected function mapToOutput(object $entity): ProductOutput
    {
        \assert($entity instanceof Product);

        return $this->mapper->mapToOutput($entity);
    }

    /**
     * A collection needs a validator of its own: without one the response falls
     * back to API Platform's body hash, which in JSON-LD changes on every
     * request because each embedded category is given a fresh random
     * blank-node @id. The most-requested endpoint would be the only uncacheable
     * one.
     *
     * The tag covers what can change the body: which products are on the page
     * and in what order (their validators, concatenated in result order),
     * totalItems (an item leaving the page changes the document while the
     * remaining items are untouched) and the request URI (it is echoed in the
     * collection's own @id and in the hydra view links) — the latter
     * canonicalised, so parameter order does not cost cache hits.
     */
    protected function onCollectionMapped(array $entities, ?int $totalItems): void
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request) {
            return;
        }

        $parts = array_map(
            static function (object $entity): string {
                \assert($entity instanceof Product);

                return ProductEtag::for($entity);
            },
            $entities,
        );

        $parts[] = 'total:'.($totalItems ?? \count($entities));
        $parts[] = 'uri:'.CanonicalQuery::of($request);

        $this->preconditions->remember('collection-'.hash('xxh128', implode('|', $parts)));
    }
}
