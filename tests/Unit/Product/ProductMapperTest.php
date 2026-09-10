<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Category\Entity\Category;
use App\Product\Dto\ProductCategoryOutput;
use App\Product\Entity\Product;
use App\Product\Mapper\ProductMapper;
use App\Tests\Support\AssignsEntityIds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductMapper::class)]
final class ProductMapperTest extends TestCase
{
    use AssignsEntityIds;

    public function testMapsEveryPublicFieldAndNothingElse(): void
    {
        $product = $this->withId(new Product('Pralka Bosch', '1299.50'), 7);
        $product->addCategory($this->withId(new Category('AGD'), 3));
        $product->addCategory($this->withId(new Category('RTV'), 4));
        $product->initialiseTimestamps();

        $output = new ProductMapper()->mapToOutput($product);

        self::assertSame(7, $output->id);
        self::assertSame('Pralka Bosch', $output->name);
        self::assertSame('1299.50', $output->price);
        self::assertSame($product->getCreatedAt(), $output->createdAt);
        self::assertSame($product->getUpdatedAt(), $output->updatedAt);

        self::assertContainsOnlyInstancesOf(ProductCategoryOutput::class, $output->categories);
        self::assertSame([3, 4], array_map(static fn (ProductCategoryOutput $c): int => $c->id, $output->categories));
        self::assertSame(['AGD', 'RTV'], array_map(static fn (ProductCategoryOutput $c): string => $c->code, $output->categories));
    }

    public function testMapsAProductWithoutCategories(): void
    {
        // The domain forbids this, but the mapper must not blow up on it — that
        // rule is enforced by validation, not by a crash here.
        $product = $this->withId(new Product('Sierota', '1.00'), 1);
        $product->initialiseTimestamps();

        self::assertSame([], new ProductMapper()->mapToOutput($product)->categories);
    }

    public function testRefusesToMapAnUnpersistedProduct(): void
    {
        $product = new Product('Bez id', '1.00');
        $product->initialiseTimestamps();

        $this->expectException(\LogicException::class);

        new ProductMapper()->mapToOutput($product);
    }
}
