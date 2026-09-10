<?php

declare(strict_types=1);

namespace App\Product\Service;

use App\Product\Entity\Product;

/**
 * Pads a validated decimal string to exactly the scale the column stores, so a
 * value read back compares equal to the one written ("1299" and "1299.5" both
 * become "1299.50").
 *
 * Deliberately string arithmetic. number_format((float) $price, 2) would give
 * the same answer for ordinary prices while routing money through a binary
 * float — the rounding this project picked DECIMAL to avoid.
 */
final readonly class PriceNormalizer
{
    /**
     * Expects a value already validated by #[DecimalPrecision]; anything else is
     * a programming error, not user input.
     */
    public function normalise(string $price): string
    {
        [$integerPart, $decimalPart] = array_pad(explode('.', $price, 2), 2, '');

        return $integerPart.'.'.str_pad($decimalPart, Product::PRICE_SCALE, '0');
    }
}
