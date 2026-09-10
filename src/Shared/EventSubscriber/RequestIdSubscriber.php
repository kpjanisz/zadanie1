<?php

declare(strict_types=1);

namespace App\Shared\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gives every request a correlation id, echoed back in X-Request-Id.
 *
 * Notifications are dispatched as a side effect of a write and land in a
 * different log line than the request that caused them; without a shared id
 * there is no way to tie "the e-mail channel failed" back to the POST that
 * triggered it.
 *
 * An inbound id is accepted so a trace can span services, but only after being
 * validated: the value ends up in log lines, and an unchecked one is a log
 * injection vector (newlines, control characters, unbounded length).
 */
final class RequestIdSubscriber
{
    public const string ATTRIBUTE = '_request_id';
    public const string HEADER = 'X-Request-Id';

    private const string SAFE_FORMAT = '/^[A-Za-z0-9._-]{8,64}$/';

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 300)]
    public function assign(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $request->attributes->set(self::ATTRIBUTE, $this->resolve($request));
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function expose(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $id = $event->getRequest()->attributes->get(self::ATTRIBUTE);

        if (\is_string($id)) {
            $event->getResponse()->headers->set(self::HEADER, $id);
        }
    }

    private function resolve(Request $request): string
    {
        $inbound = $request->headers->get(self::HEADER);

        if (\is_string($inbound) && 1 === preg_match(self::SAFE_FORMAT, $inbound)) {
            return $inbound;
        }

        return bin2hex(random_bytes(8));
    }
}
