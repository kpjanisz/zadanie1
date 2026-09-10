<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns a matching If-None-Match into 304 Not Modified.
 *
 * API Platform computes an ETag for every cacheable response
 * (ApiPlatform\HttpCache\State\AddHeadersProcessor, xxh3 of the body) but nothing
 * ever compares it against the request: Symfony only does that inside its own
 * reverse proxy or for controllers carrying #[Cache]. Without this listener the
 * header is decorative — a client that revalidates correctly still receives the
 * full body with 200.
 *
 * Applies to safe methods only, and only to successful responses that actually
 * carry an ETag; a 304 on anything else would be wrong.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class ConditionalRequestSubscriber
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->isMethodCacheable() || !$request->headers->has('If-None-Match')) {
            return;
        }

        $response = $event->getResponse();

        if (!$response->isSuccessful() || null === $response->getEtag()) {
            return;
        }

        // Mutates the response into a bodyless 304 when the validator matches.
        $response->isNotModified($request);
    }
}
