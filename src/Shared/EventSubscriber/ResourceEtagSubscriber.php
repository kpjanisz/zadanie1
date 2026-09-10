<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use App\Shared\Http\ResourceEtag;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Replaces API Platform's body-hash ETag with the state-derived one a provider
 * left on the request.
 *
 * Two reasons. First, the body hash cannot be computed before a write is
 * processed, so If-Match could never be checked against it. Second, the JSON-LD
 * representation embeds a randomly regenerated blank-node @id, which makes the
 * body hash different on every single request and the ETag useless for caching.
 *
 * Runs at a higher priority than ConditionalRequestSubscriber so the 304
 * comparison uses this validator. Knows nothing about which resource produced
 * it — only that something put a string in the agreed attribute.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: 10)]
final readonly class ResourceEtagSubscriber
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $etag = $event->getRequest()->attributes->get(ResourceEtag::REQUEST_ATTRIBUTE);

        if (!\is_string($etag)) {
            return;
        }

        $response = $event->getResponse();

        if (!$response->isSuccessful()) {
            return;
        }

        $response->setEtag($etag);
    }
}
