<?php

declare(strict_types=1);

namespace App\Category\Http;

use App\Category\Entity\Category;

/**
 * Strong validator for a category's representation.
 *
 * Simpler than a product's: a category embeds nothing that belongs to another
 * resource, so its own version fully describes what a client would receive.
 */
final readonly class CategoryEtag
{
    public static function for(Category $category): string
    {
        // State fingerprint only; PreconditionChecker adds quoting and format.
        return \sprintf('%d-%d', $category->getId() ?? 0, $category->getVersion());
    }
}
