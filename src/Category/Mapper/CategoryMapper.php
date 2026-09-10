<?php

declare(strict_types=1);

namespace App\Category\Mapper;

use App\Category\Dto\CategoryOutput;
use App\Category\Entity\Category;

/**
 * Single place that turns the entity into its public shape. Shared by the
 * providers and the processor so the two can never drift apart.
 */
final readonly class CategoryMapper
{
    public function mapToOutput(Category $category): CategoryOutput
    {
        return new CategoryOutput(
            id: $category->getId() ?? throw new \LogicException('Cannot map a category that has not been persisted.'),
            code: $category->getCode(),
            version: $category->getVersion(),
            createdAt: $category->getCreatedAt(),
            updatedAt: $category->getUpdatedAt(),
        );
    }
}
