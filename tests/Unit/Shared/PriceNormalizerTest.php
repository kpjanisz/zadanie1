<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Product\Service\PriceNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PriceNormalizer::class)]
final class PriceNormalizerTest extends TestCase
{
    #[DataProvider('prices')]
    public function testPadsToTheScaleTheColumnStores(string $input, string $expected): void
    {
        self::assertSame($expected, new PriceNormalizer()->normalise($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function prices(): iterable
    {
        yield 'integer' => ['1299', '1299.00'];
        yield 'one decimal' => ['1299.5', '1299.50'];
        yield 'two decimals' => ['1299.50', '1299.50'];
        yield 'zero' => ['0', '0.00'];
        yield 'sub-unit' => ['0.07', '0.07'];
        yield 'largest the column holds' => ['9999999999.99', '9999999999.99'];
    }

    /**
     * The value 0.1 + 0.2 cannot be represented exactly in binary floating point.
     * Normalising through a float would be a silent source of lost cents, so the
     * implementation must stay on strings.
     */
    public function testDoesNotRouteTheValueThroughAFloat(): void
    {
        $awkward = '0.30';

        self::assertSame($awkward, new PriceNormalizer()->normalise($awkward));
        self::assertSame('8039.10', new PriceNormalizer()->normalise('8039.1'));
    }
}
