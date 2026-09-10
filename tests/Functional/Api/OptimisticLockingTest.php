<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Product\Repository\ProductRepository;
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class OptimisticLockingTest extends ApiWebTestCase
{
    public function testUpdatingAProductAdvancesItsVersion(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $product = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Wersjonowany', 'price' => '1.00', 'categoryIds' => [$category->getId()]],
        ])->toArray();

        self::assertSame(1, $this->versionOf($product['id']));

        $client->request('PATCH', '/api/products/'.$product['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Zmieniony'],
        ]);
        self::assertResponseIsSuccessful();

        self::assertSame(2, $this->versionOf($product['id']));
    }

    /**
     * The lost-update scenario, made deterministic: the row is changed behind
     * the EntityManager's back — exactly what a concurrent request would do
     * between this one's read and its write.
     */
    public function testAConcurrentWriteIsRefusedInsteadOfSilentlyOverwriting(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $created = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Sporny', 'price' => '1.00', 'categoryIds' => [$category->getId()]],
        ])->toArray();

        $products = static::getContainer()->get(ProductRepository::class);
        self::assertInstanceOf(ProductRepository::class, $products);

        $product = $products->find($created['id']);
        self::assertNotNull($product);

        // Somebody else committed in the meantime.
        $this->entityManager()->getConnection()->executeStatement(
            'UPDATE product SET name = :name, version = version + 1 WHERE id = :id',
            ['name' => 'Wersja konkurenta', 'id' => $created['id']],
        );

        $product->setName('Moja wersja');

        $this->expectException(OptimisticLockException::class);
        $this->entityManager()->flush();
    }

    private function versionOf(int $id): int
    {
        return (int) $this->entityManager()->getConnection()
            ->fetchOne('SELECT version FROM product WHERE id = :id', ['id' => $id]);
    }
}
