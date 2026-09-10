<?php

declare(strict_types=1);

namespace App\Tests\Performance;

use App\Tests\Functional\Api\ApiWebTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards the write path against re-reading what has already been loaded.
 *
 * The provider declared on Patch/Delete loads the product with its categories
 * and hands the processor a DTO. If the processor then resolves the entity with
 * a DQL query it issues the very same JOIN a second time, because DQL bypasses
 * Doctrine's identity map — find() does not.
 *
 * Counting Com_select rather than total statements keeps the assertion exact:
 * UPDATE, INSERT and the counter's own SHOW STATUS are not SELECTs.
 */
#[CoversNothing]
final class WriteQueryCountTest extends ApiWebTestCase
{
    /** JWT user lookup + the product, loaded once by the provider. */
    private const int READS_PER_WRITE = 2;

    /**
     * Doctrine re-reads the version column after an UPDATE to refresh the value
     * it holds in memory (BasicEntityPersister::assignDefaultVersionValue).
     * Inherent to #[ORM\Version], and the reason a PATCH reads once more than a
     * DELETE.
     */
    private const int VERSION_REFRESH = 1;

    public function testPatchReadsTheProductOnlyOnce(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $product = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Mierzony', 'price' => '10.00', 'categoryIds' => [$category->getId()]],
        ])->toArray();

        $before = $this->selectCount();
        $client->request('PATCH', '/api/products/'.$product['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Zmieniony'],
        ]);
        self::assertResponseIsSuccessful();
        $selects = $this->selectCount() - $before;

        self::assertSame(
            self::READS_PER_WRITE + self::VERSION_REFRESH,
            $selects,
            \sprintf('A PATCH performed %d SELECTs; one more means the processor re-queried the product the provider had already loaded.', $selects),
        );
    }

    public function testDeleteReadsTheProductOnlyOnce(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $product = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Do usunięcia', 'price' => '10.00', 'categoryIds' => [$category->getId()]],
        ])->toArray();

        $before = $this->selectCount();
        $client->request('DELETE', '/api/products/'.$product['id']);
        self::assertResponseStatusCodeSame(204);

        // No UPDATE, so no version refresh — a deletion reads exactly twice.
        self::assertSame(self::READS_PER_WRITE, $this->selectCount() - $before);
    }

    private function selectCount(): int
    {
        /** @var array{Value: string}|false $row */
        $row = $this->entityManager()->getConnection()
            ->fetchAssociative("SHOW GLOBAL STATUS LIKE 'Com_select'");

        self::assertIsArray($row);

        return (int) $row['Value'];
    }
}
