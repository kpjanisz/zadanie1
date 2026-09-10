<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Notification\Repository\OperationLogRepository;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ProductApiTest extends ApiWebTestCase
{
    public function testCreatingAProductReturnsItWithItsCategoriesAndLeavesAnAuditTrail(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $agd = $this->createCategory('AGD');
        $rtv = $this->createCategory('RTV');

        $response = $this->clientFor(self::ADMIN_EMAIL)->request('POST', '/api/products', [
            'json' => [
                'name' => 'Pralka Bosch',
                'price' => '1299.5',
                'categoryIds' => [$agd->getId(), $rtv->getId()],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            'name' => 'Pralka Bosch',
            // Normalised to the DECIMAL(12,2) the column actually holds.
            'price' => '1299.50',
        ]);

        /** @var array{id: int, categories: list<array{code: string}>} $product */
        $product = $response->toArray();
        self::assertSame(['AGD', 'RTV'], array_column($product['categories'], 'code'));

        // The notification fan-out is a side effect of the write, so it is
        // observable here: the audit channel wrote a row.
        $logs = static::getContainer()->get(OperationLogRepository::class);
        self::assertInstanceOf(OperationLogRepository::class, $logs);

        $entries = $logs->findForSubject('Product', $product['id']);
        self::assertCount(1, $entries);
        self::assertSame('created', $entries[0]->getAction()->value);
    }

    public function testAProductMustBelongToAtLeastOneCategory(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);

        $this->clientFor(self::ADMIN_EMAIL)->request('POST', '/api/products', [
            'json' => ['name' => 'Bez kategorii', 'price' => '10.00', 'categoryIds' => []],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'categoryIds']]]);
    }

    public function testAnUnknownCategoryIsRejectedRatherThanSilentlyDropped(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);

        $this->clientFor(self::ADMIN_EMAIL)->request('POST', '/api/products', [
            'json' => ['name' => 'Widmo', 'price' => '10.00', 'categoryIds' => [999999]],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testPriceIsRejectedWhenItHasMoreDecimalsThanTheColumnHolds(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');

        $this->clientFor(self::ADMIN_EMAIL)->request('POST', '/api/products', [
            'json' => ['name' => 'Za dokladna', 'price' => '19.999', 'categoryIds' => [$category->getId()]],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'price']]]);
    }

    /**
     * Doctrine does not consider a changed ManyToMany collection a change to the
     * owning entity, so this is the case where updatedAt would silently not move
     * and no UPDATE would be issued at all.
     *
     * The timestamp column has second resolution, so the row is backdated first:
     * comparing two writes that land in the same second would pass or fail
     * depending on when the suite happens to run.
     */
    public function testReplacingOnlyTheCategoriesStillBumpsUpdatedAtAndNotifies(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $agd = $this->createCategory('AGD');
        $rtv = $this->createCategory('RTV');

        $client = $this->clientFor(self::ADMIN_EMAIL);

        $created = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Pralka', 'price' => '1000.00', 'categoryIds' => [$agd->getId()]],
        ])->toArray();

        $backdated = '2020-01-01 00:00:00';
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE product SET updated_at = :ts WHERE id = :id',
            ['ts' => $backdated, 'id' => $created['id']],
        );

        $updated = $client->request('PATCH', '/api/products/'.$created['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['categoryIds' => [$rtv->getId()]],
        ])->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame(['RTV'], array_column($updated['categories'], 'code'));

        $persisted = $this->entityManager()->getConnection()->fetchOne(
            'SELECT updated_at FROM product WHERE id = :id',
            ['id' => $created['id']],
        );
        self::assertIsString($persisted);
        self::assertGreaterThan(
            $backdated,
            $persisted,
            'A categories-only change must still persist a new updatedAt.',
        );

        $logs = static::getContainer()->get(OperationLogRepository::class);
        self::assertInstanceOf(OperationLogRepository::class, $logs);

        $actions = array_map(
            static fn ($entry): string => $entry->getAction()->value,
            $logs->findForSubject('Product', $created['id']),
        );
        self::assertEqualsCanonicalizing(['created', 'updated'], $actions);
    }

    public function testDeletingAProduct(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');

        $client = $this->clientFor(self::ADMIN_EMAIL);
        $created = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Do skasowania', 'price' => '5.00', 'categoryIds' => [$category->getId()]],
        ])->toArray();

        $client->request('DELETE', '/api/products/'.$created['id']);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/products/'.$created['id']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheCollectionIsPaginated(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');

        $client = $this->clientFor(self::ADMIN_EMAIL);
        foreach (['A', 'B', 'C'] as $name) {
            $client->request('POST', '/api/products', [
                'json' => ['name' => $name, 'price' => '1.00', 'categoryIds' => [$category->getId()]],
            ]);
        }

        $response = $client->request('GET', '/api/products')->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame(3, $response['totalItems']);
        self::assertCount(3, $response['member']);
    }

    public function testWritingWithoutATokenIsUnauthorised(): void
    {
        $category = $this->createCategory('AGD');

        static::createClient()->request('POST', '/api/products', [
            'json' => ['name' => 'Anonim', 'price' => '1.00', 'categoryIds' => [$category->getId()]],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * IS_AUTHENTICATED_FULLY guards against strangers; ROLE_ADMIN guards against
     * ordinary users. This covers the second.
     */
    public function testAnAuthenticatedNonAdminMayReadButNotWrite(): void
    {
        $this->createUser(self::VIEWER_EMAIL);
        $category = $this->createCategory('AGD');

        $client = $this->clientFor(self::VIEWER_EMAIL);

        $client->request('GET', '/api/products');
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/products', [
            'json' => ['name' => 'Nieuprawniony', 'price' => '1.00', 'categoryIds' => [$category->getId()]],
        ]);
        self::assertResponseStatusCodeSame(403);
    }
}
