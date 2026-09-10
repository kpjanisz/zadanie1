<?php

declare(strict_types=1);

namespace App\Product\Mapper;

use App\Product\Dto\ProductCategoryOutput;
use App\Product\Dto\ProductOutput;
use App\Product\Entity\Product;

final readonly class ProductMapper
{
    public function mapToOutput(Product $product): ProductOutput
    {
        $categories = [];
        foreach ($product->getCategories() as $category) {
            $categories[] = new ProductCategoryOutput(
                id: $category->getId() ?? throw new \LogicException('Encountered an unpersisted category.'),
                code: $category->getCode(),
            );
        }

        return new ProductOutput(
            id: $product->getId() ?? throw new \LogicException('Cannot map a product that has not been persisted.'),
            name: $product->getName(),
            version: $product->getVersion(),
            price: $product->getPrice(),
            categories: $categories,
            createdAt: $product->getCreatedAt(),
            updatedAt: $product->getUpdatedAt(),
        );
    }
}
