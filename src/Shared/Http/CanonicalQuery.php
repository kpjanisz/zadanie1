<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * A request's path and query in a form that does not depend on the order the
 * client happened to write its top-level parameters in.
 *
 * Used when a query takes part in a cache validator. `?page=1&itemsPerPage=2`
 * and `?itemsPerPage=2&page=1` are the same request returning the same
 * document, so giving them different validators costs cache hits for nothing.
 *
 * Sorting stops at the top level, deliberately. Inside a parameter the order of
 * keys can carry meaning: `order[price]=asc&order[name]=desc` sorts by price
 * first and breaks ties by name, which is not the same query as
 * `order[name]=desc&order[price]=asc`. Sorting those together would hand two
 * different requests one validator — and a client offering it would be told
 * 304 for a document it has never seen. That is the opposite of the safe
 * direction, so it is not a trade worth making.
 *
 * The cost is that genuinely unordered nested parameters — `price[gte]` and
 * `price[lte]` — are no longer normalised, so writing them the other way round
 * misses the cache. A miss is a wasted request; a wrong 304 is a wrong answer.
 * Telling the two kinds of nesting apart would mean a list of order-sensitive
 * parameter names, which is more machinery than the lost hits are worth.
 */
final readonly class CanonicalQuery
{
    public static function of(Request $request): string
    {
        $parameters = $request->query->all();
        ksort($parameters);

        $query = http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986);

        return $request->getPathInfo().('' === $query ? '' : '?'.$query);
    }
}
