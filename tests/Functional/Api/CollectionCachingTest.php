<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The product collection is the most requested endpoint, and in JSON-LD it used
 * to be the only uncacheable one: every embedded category is given a fresh
 * random blank-node @id, so API Platform's body-hash ETag differed on every
 * request. The collection therefore derives a validator of its own.
 */
#[CoversNothing]
final class CollectionCachingTest extends ApiWebTestCase
{
    #[DataProvider('formats')]
    public function testTheCollectionValidatorIsStableAndRevalidates(string $format): void
    {
        $client = $this->clientWithProducts(3);

        $first = $client->request('GET', '/api/products', ['headers' => ['Accept' => $format]]);
        $etag = $first->getHeaders()['etag'][0];

        $second = $client->request('GET', '/api/products', ['headers' => ['Accept' => $format]]);
        self::assertSame($etag, $second->getHeaders()['etag'][0], 'Two identical requests must agree.');

        $client->request('GET', '/api/products', [
            'headers' => ['Accept' => $format, 'If-None-Match' => $etag],
        ]);
        self::assertResponseStatusCodeSame(304);

        $notModified = $client->getResponse();
        self::assertNotNull($notModified);
        self::assertSame('', $notModified->getContent(false));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function formats(): iterable
    {
        yield 'JSON-LD' => ['application/ld+json'];
        yield 'plain JSON' => ['application/json'];
    }

    public function testAddingAProductInvalidatesTheCollection(): void
    {
        $client = $this->clientWithProducts(2);

        $etag = $client->request('GET', '/api/products')->getHeaders()['etag'][0];

        $category = $this->createCategory('RTV');
        $client->request('POST', '/api/products', [
            'json' => ['name' => 'Dopisany', 'price' => '1.00', 'categoryIds' => [$category->getId()]],
        ]);
        self::assertResponseStatusCodeSame(201);

        self::assertNotSame($etag, $client->request('GET', '/api/products')->getHeaders()['etag'][0]);

        $client->request('GET', '/api/products', ['headers' => ['If-None-Match' => $etag]]);
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * An item leaving the page changes the document even though every remaining
     * item is untouched — so the validator has to cover the page's composition,
     * not just the contents of its members.
     */
    public function testShrinkingThePageInvalidatesTheCollection(): void
    {
        $client = $this->clientWithProducts(3);

        $etag = $client->request('GET', '/api/products')->getHeaders()['etag'][0];

        $products = $client->request('GET', '/api/products')->toArray();
        $client->request('DELETE', '/api/products/'.$products['member'][0]['id']);
        self::assertResponseStatusCodeSame(204);

        self::assertNotSame($etag, $client->request('GET', '/api/products')->getHeaders()['etag'][0]);
    }

    public function testDifferentQueriesGetDifferentValidators(): void
    {
        $client = $this->clientWithProducts(3);

        $plain = $client->request('GET', '/api/products')->getHeaders()['etag'][0];
        $ordered = $client->request('GET', '/api/products?order[price]=desc')->getHeaders()['etag'][0];
        $paged = $client->request('GET', '/api/products?itemsPerPage=2')->getHeaders()['etag'][0];

        self::assertNotSame($plain, $ordered);
        self::assertNotSame($plain, $paged);
    }

    /**
     * A renamed category changes every product representation that embeds it,
     * while none of the products themselves are touched.
     */
    public function testRenamingAnEmbeddedCategoryInvalidatesTheCollection(): void
    {
        $client = $this->clientWithProducts(2);

        $etag = $client->request('GET', '/api/products')->getHeaders()['etag'][0];

        $categories = $client->request('GET', '/api/categories')->toArray();
        $client->request('PATCH', '/api/categories/'.$categories['member'][0]['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['code' => 'PRZEMIAN'],
        ]);
        self::assertResponseIsSuccessful();

        self::assertNotSame($etag, $client->request('GET', '/api/products')->getHeaders()['etag'][0]);
    }

    /**
     * Cache hits should not depend on the order a client happened to write its
     * parameters in. Failing this way is safe — a needless 200 rather than a
     * wrong 304 — which is precisely why it would never surface as a bug.
     */
    public function testParameterOrderDoesNotChangeTheValidator(): void
    {
        $client = $this->clientWithProducts(3);

        $one = $client->request('GET', '/api/products?page=1&itemsPerPage=2')->getHeaders()['etag'][0];
        $other = $client->request('GET', '/api/products?itemsPerPage=2&page=1')->getHeaders()['etag'][0];

        self::assertSame($one, $other);

        $client->request('GET', '/api/products?itemsPerPage=2&page=1', [
            'headers' => ['If-None-Match' => $one],
        ]);
        self::assertResponseStatusCodeSame(304);
    }

    /**
     * Inside order[] the sequence of keys IS the query: price-then-name is a
     * different sorting from name-then-price. Canonicalisation must not merge
     * them, or a client offering the first validator would be told 304 for a
     * document produced by the second.
     *
     * The collection is narrowed to a single product on purpose: with one item
     * both sortings return the same sequence, so nothing but the validator can
     * tell the two requests apart. On a larger page the differing item order
     * masks the defect by accident.
     */
    public function testOrderKeySequenceIsNotCanonicalisedAway(): void
    {
        $client = $this->clientWithProducts(3);

        $byPriceThenName = '/api/products?name=P0&order[price]=asc&order[name]=desc';
        $byNameThenPrice = '/api/products?name=P0&order[name]=desc&order[price]=asc';

        $first = $client->request('GET', $byPriceThenName)->getHeaders()['etag'][0];
        $second = $client->request('GET', $byNameThenPrice)->getHeaders()['etag'][0];

        self::assertNotSame($first, $second, 'Two different sortings must not share a validator.');

        $client->request('GET', $byNameThenPrice, ['headers' => ['If-None-Match' => $first]]);
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * Vary must name the headers that actually change the response. Content-Type
     * describes a request body and says nothing on a GET.
     */
    public function testVaryNamesAcceptAndNotContentType(): void
    {
        $client = $this->clientWithProducts(1);

        $vary = $client->request('GET', '/api/products')->getHeaders()['vary'];

        self::assertContains('Accept', $vary);
        self::assertContains('Authorization', $vary);
        self::assertNotContains('Content-Type', $vary);
    }

    private function clientWithProducts(int $count): \ApiPlatform\Symfony\Bundle\Test\Client
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $client = $this->clientFor(self::ADMIN_EMAIL);

        for ($i = 0; $i < $count; ++$i) {
            $client->request('POST', '/api/products', [
                'json' => ['name' => 'P'.$i, 'price' => '1.00', 'categoryIds' => [$category->getId()]],
            ]);
            self::assertResponseStatusCodeSame(201);
        }

        return $client;
    }
}
