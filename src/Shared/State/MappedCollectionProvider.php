<?php

declare(strict_types=1);

namespace App\Shared\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;

/**
 * Base for collection providers that return DTOs.
 *
 * It deliberately does NOT query anything itself: it delegates to API Platform's
 * Doctrine collection provider, so #[ApiFilter], ordering and pagination keep
 * working out of the box, and only maps the resulting entities. Rebuilding that
 * query by hand is how filters and page counts silently stop matching.
 *
 * @template TEntity of object
 * @template TOutput of object
 *
 * @implements ProviderInterface<TOutput>
 */
abstract readonly class MappedCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<TEntity> $doctrineCollectionProvider
     */
    public function __construct(
        private ProviderInterface $doctrineCollectionProvider,
    ) {
    }

    /**
     * @param TEntity $entity
     *
     * @return TOutput
     */
    abstract protected function mapToOutput(object $entity): object;

    /**
     * Called once per collection, with the entities that were mapped, in the
     * order they will appear. A module can use it to derive something about the
     * page as a whole — a cache validator, typically. No-op by default.
     *
     * @param list<TEntity> $entities
     * @param int|null $totalItems null when the result was not paginated
     */
    protected function onCollectionMapped(array $entities, ?int $totalItems): void
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return iterable<TOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $result = $this->doctrineCollectionProvider->provide($operation, $uriVariables, $context);

        // Keep the paginator: dropping it would lose totalItems and hydra:view.
        if ($result instanceof Paginator) {
            $entities = array_values(iterator_to_array($result));
            $items = $this->mapAll($entities);

            $this->onCollectionMapped($entities, (int) $result->getTotalItems());

            return new TraversablePaginator(
                new \ArrayIterator($items),
                $result->getCurrentPage(),
                $result->getItemsPerPage(),
                $result->getTotalItems(),
            );
        }

        if (is_iterable($result)) {
            $entities = array_values(\is_array($result) ? $result : iterator_to_array($result));
            $items = $this->mapAll($entities);

            $this->onCollectionMapped($entities, null);

            return $items;
        }

        return [];
    }

    /**
     * @param iterable<TEntity> $entities
     *
     * @return list<TOutput>
     */
    private function mapAll(iterable $entities): array
    {
        $items = [];
        foreach ($entities as $entity) {
            $items[] = $this->mapToOutput($entity);
        }

        return $items;
    }
}
