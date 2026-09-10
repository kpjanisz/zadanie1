<?php

declare(strict_types=1);

namespace App\Product\Dto;

/**
 * A product's view of one of its categories.
 *
 * Not an ApiResource on purpose: as a plain DTO it is embedded inline in both
 * JSON-LD and plain JSON, instead of collapsing to an IRI. Two fields keep the
 * product payload small — the full category is one GET away.
 */
final readonly class ProductCategoryOutput
{
    public function __construct(
        public int $id,
        public string $code,
    ) {
    }
}
