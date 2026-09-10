<?php

declare(strict_types=1);

namespace App\Shared\Health;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Liveness/readiness probe.
 *
 * One of the two places this project uses a controller rather than an API
 * Platform resource: it must answer without authentication, without the rate
 * limiter and without the serializer, because an orchestrator polls it
 * constantly and it has to keep working precisely when the API does not.
 *
 * The response body is a single word. A probe endpoint is reachable by anyone
 * who can reach the service, so neither the failing dependency nor its response
 * time belongs in it — those go to the log, where the operator is.
 *
 * It carries its own rate limiter rather than none: not sharing the API's quota
 * is not a reason to be unlimited, and every call costs a MySQL round trip.
 */
#[AsController]
final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
        #[Autowire(service: 'cache.auth_revocation')]
        private CacheItemPoolInterface $sharedCache,
        #[Target('healthProbeLimiter')]
        private RateLimiterFactoryInterface $limiter,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $clientIp = $this->requestStack->getMainRequest()?->getClientIp() ?? 'unknown';

        $limit = $this->limiter->create($clientIp)->consume();

        if (!$limit->isAccepted()) {
            // Built rather than thrown: an HttpException from a controller is
            // rendered as the HTML error page, and a probe consumer parses JSON.
            return new JsonResponse(
                ['status' => 'error'],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Cache-Control' => 'no-store',
                    'Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()),
                ],
            );
        }

        $checks = [
            'database' => $this->measure(fn () => $this->connection->executeQuery('SELECT 1')->fetchOne()),
            'cache' => $this->measure(fn () => $this->sharedCache->getItem('health.probe')->isHit()),
        ];

        $healthy = !\in_array('error', array_column($checks, 'status'), true);

        if (!$healthy) {
            // Which dependency broke, and how slow it was, goes to the operator
            // through the log — not to an anonymous caller, who would otherwise
            // learn the shape of the deployment and watch it degrade live.
            $this->logger->error('Health probe failed.', ['checks' => $checks]);
        }

        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'error'],
            $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
            // A probe result must never be served from a cache.
            ['Cache-Control' => 'no-store'],
        );
    }

    /**
     * @return array{status: string, ms: float}
     */
    private function measure(callable $probe): array
    {
        $started = microtime(true);

        try {
            $probe();
            $status = 'ok';
        } catch (\Throwable) {
            $status = 'error';
        }

        return ['status' => $status, 'ms' => round((microtime(true) - $started) * 1000, 2)];
    }
}
