<?php

declare(strict_types=1);

namespace App\Tests\Unit\Product;

use App\Category\Entity\Category;
use App\Category\Exception\CategoryNotFoundException;
use App\Category\Repository\CategoryRepository;
use App\Product\Exception\ProductRequiresCategoryException;
use App\Product\Service\CategoryLinker;
use App\Tests\Support\AssignsEntityIds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryLinker::class)]
final class CategoryLinkerTest extends TestCase
{
    use AssignsEntityIds;

    public function testResolvesIdsToCategories(): void
    {
        $agd = $this->category(1, 'AGD');
        $rtv = $this->category(2, 'RTV');

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with([1, 2])
            ->willReturn([$agd, $rtv]);

        self::assertSame([$agd, $rtv], new CategoryLinker($repository)->resolve([1, 2]));
    }

    /**
     * One query, never a find() per id.
     */
    public function testDeduplicatesBeforeQuerying(): void
    {
        $agd = $this->category(1, 'AGD');

        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with([1])
            ->willReturn([$agd]);

        self::assertSame([$agd], new CategoryLinker($repository)->resolve([1, 1, 1]));
    }

    /**
     * Dropping an unknown id silently would let a product end up with fewer
     * categories than the client asked for — or none at all.
     */
    public function testAnUnknownIdIsAnErrorRatherThanBeingIgnored(): void
    {
        $repository = $this->createStub(CategoryRepository::class);
        $repository->method('findByIds')->willReturn([$this->category(1, 'AGD')]);

        try {
            new CategoryLinker($repository)->resolve([1, 999]);
            self::fail('Expected the unknown id to be refused.');
        } catch (CategoryNotFoundException $e) {
            // The message is a translation key now; the id it names lives in the
            // parameters, which is what the client-facing text is built from.
            self::assertSame(CategoryNotFoundException::KEY, $e->getTranslationKey());
            self::assertSame(['%id%' => 999], $e->getTranslationParameters());
        }
    }

    public function testAnEmptyListIsRefused(): void
    {
        $repository = $this->createMock(CategoryRepository::class);
        $repository->expects(self::never())->method('findByIds');

        $this->expectException(ProductRequiresCategoryException::class);

        new CategoryLinker($repository)->resolve([]);
    }

    public function testAListThatIsOnlyDuplicatesOfNothingIsStillRefused(): void
    {
        $repository = $this->createStub(CategoryRepository::class);
        $repository->method('findByIds')->willReturn([]);

        $this->expectException(CategoryNotFoundException::class);

        new CategoryLinker($repository)->resolve([42]);
    }

    private function category(int $id, string $code): Category
    {
        return $this->withId(new Category($code), $id);
    }
}
