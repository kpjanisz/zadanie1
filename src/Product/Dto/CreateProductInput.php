<?php

declare(strict_types=1);

namespace App\Product\Dto;

use App\Product\Entity\Product;
use App\Shared\Validator\DecimalPrecision;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Whitelist of what a client may set when creating a product. Timestamps and the
 * id are the processor's and Doctrine's business, never the request's.
 */
final class CreateProductInput
{
    #[Assert\NotBlank(message: 'product.name.not_blank', normalizer: 'trim')]
    #[Assert\Length(max: Product::NAME_MAX_LENGTH, maxMessage: 'product.name.too_long')]
    public ?string $name = null;

    /**
     * A decimal STRING, e.g. "1299.00" — not a JSON number.
     *
     * Money is stored as DECIMAL and must never pass through a binary float, so
     * the wire format matches the storage format. A JSON number is rejected with
     * 400 rather than silently rounded.
     */
    #[Assert\NotNull(message: 'product.price.not_null')]
    #[Assert\Type(type: 'numeric', message: 'product.price.not_numeric')]
    #[Assert\Positive(message: 'product.price.not_positive')]
    #[DecimalPrecision(precision: Product::PRICE_PRECISION, scale: Product::PRICE_SCALE)]
    public ?string $price = null;

    /**
     * The task's "a product must belong to at least one category", expressed
     * where it can actually be enforced. MySQL cannot state it on a join table.
     *
     * @var list<int>
     */
    #[Assert\Count(min: 1, minMessage: 'product.categories.at_least_one')]
    #[Assert\Unique(message: 'product.categories.duplicated')]
    #[Assert\All([
        new Assert\Type(type: 'integer', message: 'product.categories.invalid_id'),
        new Assert\Positive(message: 'product.categories.invalid_id'),
    ])]
    public array $categoryIds = [];
}
