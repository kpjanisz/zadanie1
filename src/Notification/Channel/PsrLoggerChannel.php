<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\Contract\NotificationChannelInterface;
use App\Notification\Contract\NotificationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Mirrors every notification into the application log.
 *
 * Cheap, dependency-free, and it keeps working when the database channel cannot
 * — which is exactly when you want a trace.
 */
/**
 * Runs first: the cheapest channel, and the one whose record is most useful
 * when a later channel fails.
 */
#[AsTaggedItem(priority: 20)]
final readonly class PsrLoggerChannel implements NotificationChannelInterface
{
    public const string NAME = 'psr_logger';

    public function __construct(
        // Symfony 8.1 deprecates matching a named autowiring alias by parameter
        // name alone; the channel is named explicitly instead.
        #[Target('notification')]
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function supports(NotificationInterface $notification): bool
    {
        return true;
    }

    public function send(NotificationInterface $notification): void
    {
        $this->logger->info($notification->getType(), [
            'action' => $notification->getAction()->value,
            'subject_type' => $notification->getSubjectType(),
            'subject_id' => $notification->getSubjectId(),
            'context' => $notification->getContext(),
        ]);
    }
}
