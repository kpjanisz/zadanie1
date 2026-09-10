<?php

declare(strict_types=1);

namespace App\Product\Service;

use App\Category\Contract\CategoryUsageCheckerInterface;
use App\Product\Repository\ProductRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Product owns the ManyToMany, so Product answers whether a category is in use.
 */
#[AsAlias(CategoryUsageCheckerInterface::class)]
final readonly class ProductCategoryUsageChecker implements CategoryUsageCheckerInterface
{
    public function __construct(
        private ProductRepository $repository,
    ) {
    }

    public function countUsages(int $categoryId): int
    {
        return $this->repository->countByCategory($categoryId);
    }
}
