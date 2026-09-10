<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Notification\Service\OperationLogRecorder;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The audit trail is what makes the notification fan-out observable over the
 * API rather than only in SQL.
 */
#[CoversNothing]
final class OperationLogApiTest extends ApiWebTestCase
{
    public function testSavingAProductBecomesVisibleInTheAuditTrail(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $product = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Audytowany', 'price' => '77.00', 'categoryIds' => [$category->getId()]],
        ])->toArray();

        $logs = $client->request('GET', \sprintf(
            '/api/operation-logs?subjectType=Product&subjectId=%d',
            $product['id'],
        ))->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame(1, $logs['totalItems']);

        $entry = $logs['member'][0];
        self::assertSame(OperationLogRecorder::CHANNEL, $entry['channel']);
        self::assertSame('created', $entry['action']);
        self::assertSame('Product', $entry['subjectType']);
        self::assertSame($product['id'], $entry['subjectId']);
        self::assertSame('Audytowany', $entry['payload']['name']);
        self::assertSame('77.00', $entry['payload']['price']);
    }

    public function testTheActionFilterSeparatesCreatesFromUpdates(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $product = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Zmieniany', 'price' => '10.00', 'categoryIds' => [$category->getId()]],
        ])->toArray();

        $client->request('PATCH', '/api/products/'.$product['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Zmieniony'],
        ]);
        self::assertResponseIsSuccessful();

        $created = $client->request('GET', '/api/operation-logs?action=created')->toArray();
        $updated = $client->request('GET', '/api/operation-logs?action=updated')->toArray();

        self::assertSame(1, $created['totalItems']);
        self::assertSame(1, $updated['totalItems']);
        self::assertSame('updated', $updated['member'][0]['action']);
    }

    public function testDeletingAProductIsRecorded(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $product = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Skasowany', 'price' => '1.00', 'categoryIds' => [$category->getId()]],
        ])->toArray();

        $client->request('DELETE', '/api/products/'.$product['id']);
        self::assertResponseStatusCodeSame(204);

        $logs = $client->request('GET', '/api/operation-logs?action=deleted')->toArray();

        self::assertSame(1, $logs['totalItems']);
        self::assertSame($product['id'], $logs['member'][0]['subjectId']);
        self::assertSame('Skasowany', $logs['member'][0]['payload']['name']);
    }

    public function testNewestEntriesComeFirst(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        foreach (['A', 'B', 'C'] as $name) {
            $client->request('POST', '/api/products', [
                'json' => ['name' => $name, 'price' => '1.00', 'categoryIds' => [$category->getId()]],
            ]);
        }

        $logs = $client->request('GET', '/api/operation-logs')->toArray();

        self::assertSame(3, $logs['totalItems']);
        self::assertSame('C', $logs['member'][0]['payload']['name']);
    }

    /**
     * Append-only: the log is written by OperationLogRecorder and by nothing else.
     */
    public function testTheTrailCannotBeWrittenToOrAlteredOverTheApi(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $client->request('POST', '/api/operation-logs', ['json' => []]);
        self::assertResponseStatusCodeSame(405);

        $client->request('DELETE', '/api/operation-logs/1');
        self::assertResponseStatusCodeSame(405);
    }

    public function testTheTrailIsAdminOnly(): void
    {
        $this->createUser(self::VIEWER_EMAIL);

        $this->clientFor(self::VIEWER_EMAIL)->request('GET', '/api/operation-logs');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnUnknownEntryIsNotFound(): void
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);

        $this->clientFor(self::ADMIN_EMAIL)->request('GET', '/api/operation-logs/999999');

        self::assertResponseStatusCodeSame(404);
    }
}
