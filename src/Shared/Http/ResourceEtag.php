<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Where a state provider leaves the validator it computed, so the response
 * listener can put it on the response.
 *
 * Only the channel lives here — Shared holds no opinion about what a validator
 * is made of. Each module computes its own (see Product\Http\ProductEtag) and
 * writes it into this attribute. Putting the computation here instead would
 * make the generic layer depend on a domain module.
 */
final readonly class ResourceEtag
{
    public const string REQUEST_ATTRIBUTE = '_resource_etag';
}
