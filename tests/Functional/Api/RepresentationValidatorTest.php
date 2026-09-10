<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A validator identifies one representation, not one resource.
 *
 * The ETags here are derived from entity state, which says nothing about
 * format — yet the same product is 343 bytes as JSON-LD and 172 as plain JSON.
 * Vary: Accept keeps a shared cache honest, but not a client: it would offer the
 * validator it received for JSON-LD while asking for JSON, be told 304, and use
 * the JSON-LD copy as the answer.
 */
#[CoversNothing]
final class RepresentationValidatorTest extends ApiWebTestCase
{
    private const string LD = 'application/ld+json';
    private const string PLAIN = 'application/json';

    #[DataProvider('resources')]
    public function testTheTwoFormatsGetDifferentValidators(string $path): void
    {
        $client = $this->seed();
        $path = $this->resolve($path);

        self::assertNotSame(
            $this->etagOf($client, $path, self::LD),
            $this->etagOf($client, $path, self::PLAIN),
            'Two documents of different length cannot share one validator.',
        );
    }

    #[DataProvider('resources')]
    public function testAValidatorFromOneFormatDoesNotRevalidateTheOther(string $path): void
    {
        $client = $this->seed();
        $path = $this->resolve($path);
        $fromLd = $this->etagOf($client, $path, self::LD);

        $client->request('GET', $path, [
            'headers' => ['Accept' => self::PLAIN, 'If-None-Match' => $fromLd],
        ]);

        self::assertResponseStatusCodeSame(200, 'A 304 here would hand the client the wrong document.');
    }

    #[DataProvider('resources')]
    public function testEachFormatStillRevalidatesAgainstItsOwnValidator(string $path): void
    {
        $client = $this->seed();
        $path = $this->resolve($path);

        foreach ([self::LD, self::PLAIN] as $format) {
            $etag = $this->etagOf($client, $path, $format);

            $client->request('GET', $path, ['headers' => ['Accept' => $format, 'If-None-Match' => $etag]]);
            self::assertResponseStatusCodeSame(304, \sprintf('%s stopped revalidating.', $format));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function resources(): iterable
    {
        yield 'product item' => ['/api/products/PRODUCT_ID'];
        yield 'product collection' => ['/api/products'];
        yield 'category item' => ['/api/categories/CATEGORY_ID'];
        yield 'category collection' => ['/api/categories'];
    }

    private function etagOf(object $client, string $path, string $format): string
    {
        \assert($client instanceof \ApiPlatform\Symfony\Bundle\Test\Client);

        $headers = $client->request('GET', $path, ['headers' => ['Accept' => $format]])->getHeaders();
        self::assertArrayHasKey('etag', $headers, \sprintf('%s carries no validator at all.', $path));

        return $headers['etag'][0];
    }

    private ?int $productId = null;
    private ?int $categoryId = null;

    private function resolve(string $path): string
    {
        return str_replace(
            ['PRODUCT_ID', 'CATEGORY_ID'],
            [(string) $this->productId, (string) $this->categoryId],
            $path,
        );
    }

    private function seed(): \ApiPlatform\Symfony\Bundle\Test\Client
    {
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $category = $this->createCategory('AGD');
        $this->categoryId = $category->getId();

        $client = $this->clientFor(self::ADMIN_EMAIL);

        $product = $client->request('POST', '/api/products', [
            'json' => ['name' => 'Reprezentacja', 'price' => '10.00', 'categoryIds' => [$this->categoryId]],
        ])->toArray();
        $this->productId = $product['id'];

        return $client;
    }
}
