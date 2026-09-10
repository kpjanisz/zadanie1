<?php

declare(strict_types=1);

namespace App\Product\State;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Product\Entity\Product;
use Doctrine\ORM\QueryBuilder;

/**
 * Joins each product's categories into the collection query.
 *
 * Without this the mapper triggers one SELECT per product: API Platform's own
 * eager-loading extension cannot help here, because the exposed resource is a
 * DTO (ProductOutput) whose `categories` property is another DTO rather than a
 * mapped association — there is nothing for it to infer a join from.
 *
 * Implemented as a query extension rather than a custom repository method so the
 * #[ApiFilter] filters, ordering and pagination keep operating on the same query
 * builder. Doctrine's paginator wraps a to-many fetch-join in a subquery, so the
 * row multiplication does not corrupt totalItems or the page size.
 */
final readonly class ProductCategoriesEagerLoadingExtension implements QueryCollectionExtensionInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Product::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0] ?? null;

        if (null === $rootAlias) {
            return;
        }

        $categoriesAlias = $queryNameGenerator->generateJoinAlias('categories');

        $queryBuilder
            ->leftJoin(\sprintf('%s.categories', $rootAlias), $categoriesAlias)
            ->addSelect($categoriesAlias);
    }
}
