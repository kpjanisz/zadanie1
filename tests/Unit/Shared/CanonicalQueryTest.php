<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Http\CanonicalQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CanonicalQuery::class)]
final class CanonicalQueryTest extends TestCase
{
    #[DataProvider('equivalentPairs')]
    public function testTheSameRequestWrittenDifferentlyCanonicalisesTheSame(string $left, string $right): void
    {
        self::assertSame(
            CanonicalQuery::of(Request::create($left)),
            CanonicalQuery::of(Request::create($right)),
            'Parameter order is not part of what a request asks for.',
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function equivalentPairs(): iterable
    {
        yield 'two scalars swapped' => [
            '/api/products?page=1&itemsPerPage=2',
            '/api/products?itemsPerPage=2&page=1',
        ];

        yield 'filters swapped' => [
            '/api/products?name=pralka&categories.code=AGD',
            '/api/products?categories.code=AGD&name=pralka',
        ];

        // The top level is normalised even when one of its values is nested.
        yield 'nested and scalar swapped' => [
            '/api/products?order[price]=asc&page=2',
            '/api/products?page=2&order[price]=asc',
        ];
    }

    #[DataProvider('differentRequests')]
    public function testRequestsThatDifferStayDifferent(string $left, string $right): void
    {
        self::assertNotSame(
            CanonicalQuery::of(Request::create($left)),
            CanonicalQuery::of(Request::create($right)),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function differentRequests(): iterable
    {
        yield 'different page' => ['/api/products?page=1', '/api/products?page=2'];
        yield 'different value' => ['/api/products?order[price]=asc', '/api/products?order[price]=desc'];
        yield 'extra parameter' => ['/api/products?page=1', '/api/products?page=1&itemsPerPage=5'];
        yield 'different path' => ['/api/products', '/api/categories'];

        // The case that forbids recursive sorting: inside order[] the sequence
        // of keys IS the query. Sorting them together would give two different
        // sortings one validator, and a client offering it would be told 304
        // for a document it has never seen.
        yield 'order keys in a different sequence' => [
            '/api/products?order[price]=asc&order[name]=desc',
            '/api/products?order[name]=desc&order[price]=asc',
        ];

        yield 'three order keys rotated' => [
            '/api/products?order[price]=asc&order[name]=asc&order[id]=asc',
            '/api/products?order[name]=asc&order[id]=asc&order[price]=asc',
        ];

        // Accepted cost of not recursing: these two mean the same thing but no
        // longer share a validator. A missed cache hit, not a wrong answer.
        yield 'unordered nested keys swapped (accepted cache miss)' => [
            '/api/products?price[gte]=100&price[lte]=900',
            '/api/products?price[lte]=900&price[gte]=100',
        ];
    }

    public function testAQuerylessRequestIsJustItsPath(): void
    {
        self::assertSame('/api/products', CanonicalQuery::of(Request::create('/api/products')));
    }
}
