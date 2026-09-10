<?php

declare(strict_types=1);

namespace App\Product\Dto;

use App\Product\Entity\Product;
use App\Shared\Validator\DecimalPrecision;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Partial update: null means "not supplied". A supplied value still has to be
 * valid — in particular an explicitly empty category list is rejected, because a
 * product may never end up with none.
 */
final class UpdateProductInput
{
    #[Assert\NotBlank(allowNull: true, message: 'product.name.not_blank', normalizer: 'trim')]
    #[Assert\Length(max: Product::NAME_MAX_LENGTH, maxMessage: 'product.name.too_long')]
    public ?string $name = null;

    #[Assert\Type(type: 'numeric', message: 'product.price.not_numeric')]
    #[Assert\Positive(message: 'product.price.not_positive')]
    #[DecimalPrecision(precision: Product::PRICE_PRECISION, scale: Product::PRICE_SCALE)]
    public ?string $price = null;

    /**
     * @var list<int>|null
     */
    #[Assert\Count(min: 1, minMessage: 'product.categories.at_least_one')]
    #[Assert\Unique(message: 'product.categories.duplicated')]
    #[Assert\All([
        new Assert\Type(type: 'integer', message: 'product.categories.invalid_id'),
        new Assert\Positive(message: 'product.categories.invalid_id'),
    ])]
    public ?array $categoryIds = null;
}
