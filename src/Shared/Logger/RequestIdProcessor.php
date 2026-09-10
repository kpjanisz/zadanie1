<?php

declare(strict_types=1);

namespace App\Shared\Logger;

use App\Shared\EventSubscriber\RequestIdSubscriber;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stamps every log record with the correlation id of the request that produced
 * it, so a failed notification can be traced back to the write that caused it.
 */
final readonly class RequestIdProcessor implements ProcessorInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $id = $this->requestStack->getMainRequest()?->attributes->get(RequestIdSubscriber::ATTRIBUTE);

        if (\is_string($id)) {
            $record->extra['request_id'] = $id;
        }

        return $record;
    }
}
