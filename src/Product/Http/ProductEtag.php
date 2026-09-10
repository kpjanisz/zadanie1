<?php

declare(strict_types=1);

namespace App\Product\Http;

use App\Category\Entity\Category;
use App\Product\Entity\Product;

/**
 * Strong validator for a product's representation.
 *
 * Derived from state rather than from the serialised body, because the body
 * cannot be produced before a write is processed — and If-Match has to be
 * checked before it.
 *
 * The category digest is not truncated: it is the part of the validator that
 * catches a category rename while the product's own version is unchanged, so a
 * collision would mean a stale 304 or a wrongly accepted If-Match. Shortening it
 * saves nothing — an ETag has no length budget.
 *
 * The embedded categories are part of the tag: a product's representation
 * carries their codes, so renaming one changes what a client would receive and
 * must invalidate the validator. Leaving them out would let a conditional GET
 * return 304 for a body that has in fact changed.
 */
final readonly class ProductEtag
{
    public static function for(Product $product): string
    {
        $categories = array_map(
            static fn (Category $category): string => $category->getId().':'.$category->getCode(),
            $product->getCategories()->toArray(),
        );

        sort($categories);

        // The state fingerprint only. Quoting and the representation format are
        // added by PreconditionChecker, which is the one place that knows what
        // was actually negotiated.
        return \sprintf(
            '%d-%d-%s',
            $product->getId() ?? 0,
            $product->getVersion(),
            hash('xxh128', implode(',', $categories)),
        );
    }
}
