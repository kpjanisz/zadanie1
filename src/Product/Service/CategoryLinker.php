<?php

declare(strict_types=1);

namespace App\Product\Service;

use App\Category\Entity\Category;
use App\Category\Exception\CategoryNotFoundException;
use App\Category\Repository\CategoryRepository;
use App\Product\Exception\ProductRequiresCategoryException;

/**
 * Turns the ids a client sent into category entities.
 *
 * Two rules live here so both create and update get them identically: the list
 * may not be empty, and an unknown id is an error rather than something quietly
 * dropped — silently ignoring it would let a product end up with fewer
 * categories than the client asked for, or none at all.
 */
final readonly class CategoryLinker
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Category>
     */
    public function resolve(array $ids): array
    {
        $unique = array_values(array_unique($ids));

        if ([] === $unique) {
            throw new ProductRequiresCategoryException();
        }

        // One query, never a find() per id.
        $categories = $this->categoryRepository->findByIds($unique);

        if (\count($categories) !== \count($unique)) {
            $found = array_map(static fn (Category $category): ?int => $category->getId(), $categories);
            $missing = array_values(array_diff($unique, $found));

            throw new CategoryNotFoundException($missing[0] ?? '');
        }

        return $categories;
    }
}
