<?php

declare(strict_types=1);

namespace App\Tests\Performance;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Tests\Functional\Api\ApiWebTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Regression guard against N+1 on the product collection.
 *
 * The mapper reads every product's categories, so a naive implementation would
 * issue one extra SELECT per product. Counting is done with MySQL's own
 * Questions counter rather than Doctrine's debug middleware: the profiler is not
 * enabled in the test environment, and a counter that silently reports zero
 * would make this test pass while measuring nothing.
 */
#[CoversNothing]
final class ProductCollectionQueryCountTest extends ApiWebTestCase
{
    public function testCollectionQueryCountDoesNotGrowWithTheNumberOfProducts(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $this->createProducts($client, $category->getId(), 0, 3);
        $withThree = $this->countQueriesFor($client, '/api/products');

        $this->createProducts($client, $category->getId(), 3, 12);
        $withTwelve = $this->countQueriesFor($client, '/api/products');

        // Sanity check on the measurement itself: a request that touches the
        // database must register at least one query, otherwise the counter is
        // broken and the comparison below would be meaningless.
        self::assertGreaterThan(0, $withThree, 'The query counter is not measuring anything.');

        self::assertSame(
            $withThree,
            $withTwelve,
            \sprintf(
                'Query count grew from %d to %d as the collection went from 3 to 12 products — that is an N+1.',
                $withThree,
                $withTwelve,
            ),
        );
    }

    private function createProducts(Client $client, ?int $categoryId, int $from, int $to): void
    {
        for ($i = $from; $i < $to; ++$i) {
            $client->request('POST', '/api/products', [
                'json' => ['name' => 'P'.$i, 'price' => '1.00', 'categoryIds' => [$categoryId]],
            ]);
            self::assertResponseStatusCodeSame(201);
        }
    }

    private function countQueriesFor(Client $client, string $path): int
    {
        $before = $this->questions();
        $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $after = $this->questions();

        // Minus the SHOW STATUS statement that produced $after.
        return $after - $before - 1;
    }

    private function questions(): int
    {
        /** @var array{Variable_name: string, Value: string}|false $row */
        $row = $this->entityManager()->getConnection()
            ->fetchAssociative("SHOW GLOBAL STATUS LIKE 'Questions'");

        self::assertIsArray($row);

        return (int) $row['Value'];
    }
}
