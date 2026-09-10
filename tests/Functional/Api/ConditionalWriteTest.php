<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The read-think-write window: a client GETs a product, a person stares at the
 * form for five minutes, somebody else saves in the meantime, and the client
 * PATCHes. #[ORM\Version] cannot see this — its check only spans one request —
 * so the conditional-write headers are what close it.
 */
#[CoversNothing]
final class ConditionalWriteTest extends ApiWebTestCase
{
    public function testAWriteGuardedByAStaleIfMatchIsRefused(): void
    {
        $client = $this->clientWithProduct($id, $etag);

        // Somebody else saves first.
        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $etag],
            'json' => ['name' => 'Zmiana kolegi'],
        ]);
        self::assertResponseIsSuccessful();

        // Our client still holds the validator it read five minutes ago.
        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $etag],
            'json' => ['name' => 'Zgubiona zmiana'],
        ]);
        self::assertResponseStatusCodeSame(412);

        $current = $client->request('GET', '/api/products/'.$id)->toArray();
        self::assertSame('Zmiana kolegi', $current['name'], 'The refused write must not have landed.');
    }

    public function testAWriteGuardedByAMatchingIfMatchSucceeds(): void
    {
        $client = $this->clientWithProduct($id, $etag);

        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $etag],
            'json' => ['name' => 'Zaakceptowana'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['name' => 'Zaakceptowana', 'version' => 2]);
    }

    public function testWildcardIfMatchOnlyRequiresThatTheResourceExists(): void
    {
        $client = $this->clientWithProduct($id, $etag);

        $client->request('DELETE', '/api/products/'.$id, ['headers' => ['If-Match' => '*']]);

        self::assertResponseStatusCodeSame(204);
    }

    public function testAWriteWithoutIfMatchIsStillAccepted(): void
    {
        // Conditional writes are opt-in; a client that does not ask for them is
        // still protected inside the request by the version column.
        $client = $this->clientWithProduct($id, $etag);

        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Bez warunku'],
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testTheItemValidatorIsStableAcrossRequests(): void
    {
        $client = $this->clientWithProduct($id, $etag);

        $again = $client->request('GET', '/api/products/'.$id);
        self::assertSame($etag, $again->getHeaders()['etag'][0]);

        $client->request('GET', '/api/products/'.$id, ['headers' => ['If-None-Match' => $etag]]);
        self::assertResponseStatusCodeSame(304);
    }

    /**
     * The read and the write negotiate their formats independently: a client
     * reads JSON, then PATCHes, and the PATCH carries whatever Accept the client
     * library felt like sending — often none at all, which lands on the server's
     * preferred format instead. None of that is a change to the resource, so
     * none of it may cost a 412.
     *
     * The earlier tests all missed this because the test client happens to send
     * one identical Accept everywhere.
     *
     * @param array<string, string> $writeHeaders
     */
    #[DataProvider('writeFormats')]
    public function testAValidatorReadAsJsonAuthorisesAWriteInAnyFormat(array $writeHeaders): void
    {
        $client = $this->clientWithProduct($id, $etag, ['Accept' => 'application/json']);

        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => $writeHeaders + [
                'Content-Type' => 'application/merge-patch+json',
                'If-Match' => $etag,
            ],
            'json' => ['name' => 'Zaakceptowana'],
        ]);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function writeFormats(): iterable
    {
        yield 'same format as the read' => [['Accept' => 'application/json']];
        yield 'the other format' => [['Accept' => 'application/ld+json']];
        yield 'no preference at all' => [['Accept' => null]];
    }

    /**
     * The remedy a 412 prescribes is to re-read and retry. If the refusal is
     * about format rather than state, that loop never terminates — every pass
     * reads a validator in one format and offers it in another.
     */
    public function testTheRemedyForA412Terminates(): void
    {
        $client = $this->clientWithProduct($id, $etag, ['Accept' => 'application/json']);

        // Somebody else writes, so our validator is genuinely stale.
        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Zmiana kolegi'],
        ]);

        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $etag],
            'json' => ['name' => 'Nasza zmiana'],
        ]);
        self::assertResponseStatusCodeSame(412);

        // Re-read exactly as the client did before, retry once.
        $fresh = $client->request('GET', '/api/products/'.$id, [
            'headers' => ['Accept' => 'application/json'],
        ])->getHeaders()['etag'][0];

        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $fresh],
            'json' => ['name' => 'Nasza zmiana'],
        ]);

        self::assertResponseIsSuccessful('One retry must be enough; otherwise the client loops forever.');
    }

    public function testAValidatorStrippedOfItsFormatStillAuthorisesTheWrite(): void
    {
        // A validator that has travelled through a client library may arrive as
        // the bare state. It still names the state we are about to overwrite.
        $client = $this->clientWithProduct($id, $etag);
        $bare = preg_replace('/\.\w+"$/', '"', $etag);

        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $bare],
            'json' => ['name' => 'Bez sufiksu'],
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testAStaleValidatorIsRefusedWhateverFormatItWasReadIn(): void
    {
        // The relaxation must not have cost the guard its point.
        $client = $this->clientWithProduct($id, $etag, ['Accept' => 'application/json']);

        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Zmiana kolegi'],
        ]);
        self::assertResponseIsSuccessful();

        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $etag],
            'json' => ['name' => 'Zgubiona zmiana'],
        ]);

        self::assertResponseStatusCodeSame(412);
    }

    public function testAWeakValidatorDoesNotAuthoriseAWrite(): void
    {
        // If-Match is defined to use strong comparison: a weak tag promises
        // equivalent meaning, not identical state.
        $client = $this->clientWithProduct($id, $etag);

        $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => 'W/'.$etag],
            'json' => ['name' => 'Słaby walidator'],
        ]);

        self::assertResponseStatusCodeSame(412);
    }

    public function testACategoryWriteGuardedByAStaleIfMatchIsRefused(): void
    {
        $client = $this->clientWithCategory($id, $etag);

        $client->request('PATCH', '/api/categories/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $etag],
            'json' => ['code' => 'PIERWSZY'],
        ]);
        self::assertResponseIsSuccessful();

        $client->request('PATCH', '/api/categories/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $etag],
            'json' => ['code' => 'DRUGI'],
        ]);
        self::assertResponseStatusCodeSame(412);

        $current = $client->request('GET', '/api/categories/'.$id)->toArray();
        self::assertSame('PIERWSZY', $current['code'], 'The refused write must not have landed.');
    }

    public function testACategoryWriteGuardedByAMatchingIfMatchSucceeds(): void
    {
        $client = $this->clientWithCategory($id, $etag);

        $client->request('PATCH', '/api/categories/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json', 'If-Match' => $etag],
            'json' => ['code' => 'ZMIENIONY'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['code' => 'ZMIENIONY', 'version' => 2]);
    }

    public function testWildcardIfMatchDeletesACategory(): void
    {
        $client = $this->clientWithCategory($id, $etag);

        $client->request('DELETE', '/api/categories/'.$id, ['headers' => ['If-Match' => '*']]);

        self::assertResponseStatusCodeSame(204);
    }

    /**
     * @param-out int    $id
     * @param-out string $etag
     */
    private function clientWithCategory(?int &$id, ?string &$etag): \ApiPlatform\Symfony\Bundle\Test\Client
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $categoryId = $category->getId();
        self::assertNotNull($categoryId);

        $id = $categoryId;
        $etag = $client->request('GET', '/api/categories/'.$id)->getHeaders()['etag'][0];

        return $client;
    }

    /**
     * @param array<string, string> $readHeaders format the client reads in
     *
     * @param-out int    $id
     * @param-out string $etag
     */
    private function clientWithProduct(
        ?int &$id,
        ?string &$etag,
        array $readHeaders = [],
    ): \ApiPlatform\Symfony\Bundle\Test\Client {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        $product = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Warunkowy', 'price' => '10.00', 'categoryIds' => [$category->getId()]],
        ])->toArray();

        $id = $product['id'];
        $etag = $client->request('GET', '/api/products/'.$id, ['headers' => $readHeaders])
            ->getHeaders()['etag'][0];

        return $client;
    }
}
