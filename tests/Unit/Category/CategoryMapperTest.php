<?php

declare(strict_types=1);

namespace App\Tests\Unit\Category;

use App\Category\Entity\Category;
use App\Category\Mapper\CategoryMapper;
use App\Tests\Support\AssignsEntityIds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryMapper::class)]
final class CategoryMapperTest extends TestCase
{
    use AssignsEntityIds;

    public function testMapsEveryPublicField(): void
    {
        $category = $this->withId(new Category('AGD'), 3);
        $category->initialiseTimestamps();

        $output = new CategoryMapper()->mapToOutput($category);

        self::assertSame(3, $output->id);
        self::assertSame('AGD', $output->code);
        self::assertSame($category->getCreatedAt(), $output->createdAt);
        self::assertSame($category->getUpdatedAt(), $output->updatedAt);
    }

    public function testRefusesToMapAnUnpersistedCategory(): void
    {
        $this->expectException(\LogicException::class);

        new CategoryMapper()->mapToOutput(new Category('AGD'));
    }
}
