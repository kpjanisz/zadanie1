<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Per-IP backstop on everything under /api.
 *
 * Runs at a high priority, before routing and the firewall, so a flood is
 * rejected before any expensive work happens. The client IP comes from
 * Request::getClientIp(), which is only trustworthy because
 * framework.trusted_proxies is configured — without it every request would look
 * like it came from nginx and the whole world would share one quota.
 *
 * This is an application-level guard against scripted abuse; a volumetric L3/L4
 * flood belongs on the edge, not here.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 250)]
final readonly class ApiRateLimitSubscriber
{
    public function __construct(
        #[Target('apiGlobalLimiter')]
        private RateLimiterFactoryInterface $limiter,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // A CORS preflight is answered by NelmioCorsBundle without touching the
        // application, the serializer or the database. Counting it would spend
        // half of a browser client's quota on requests that cost nothing —
        // every cross-origin write is preceded by one. Volumetric abuse of
        // OPTIONS itself belongs on the edge, like any other flood.
        if ($request->isMethod(Request::METHOD_OPTIONS) && $request->headers->has('Access-Control-Request-Method')) {
            return;
        }

        $limit = $this->limiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume();

        if ($limit->isAccepted()) {
            return;
        }

        // The response is built here rather than thrown as an HttpException: at
        // this priority API Platform has not negotiated the request format yet,
        // so an exception would be rendered as the HTML error page instead of
        // the RFC 7807 document every other API error uses.
        $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

        $event->setResponse(new JsonResponse(
            [
                'type' => '/errors/429',
                'title' => 'Too Many Requests',
                'status' => Response::HTTP_TOO_MANY_REQUESTS,
                'detail' => $this->translator->trans('rate_limit.too_many_requests'),
            ],
            Response::HTTP_TOO_MANY_REQUESTS,
            [
                'Content-Type' => 'application/problem+json',
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Limit' => (string) $limit->getLimit(),
                'X-RateLimit-Remaining' => (string) $limit->getRemainingTokens(),
            ],
        ));

        $event->stopPropagation();
    }
}
