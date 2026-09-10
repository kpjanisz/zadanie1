<?php

declare(strict_types=1);

namespace App\Notification;

use App\Notification\Contract\NotificationChannelInterface;
use App\Notification\Contract\NotificationInterface;
use App\Notification\Dto\DispatchReport;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Fans a notification out to every registered channel.
 *
 * Channels arrive through the tag, not through a hard-coded list or a match(),
 * so a new channel needs no change here.
 */
final readonly class NotificationDispatcher
{
    /**
     * @param iterable<NotificationChannelInterface> $channels
     */
    public function __construct(
        #[AutowireIterator('app.notification_channel')]
        private iterable $channels,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Notifying is a side effect of a write that already succeeded, so a channel
     * failure must never propagate: an unreachable SMTP server cannot be allowed
     * to turn a successful POST /api/products into a 500, nor to stop the
     * remaining channels from delivering.
     *
     * Failures are logged and reported, never swallowed silently.
     */
    public function dispatch(NotificationInterface $notification): DispatchReport
    {
        $delivered = [];
        $failed = [];

        foreach ($this->channels as $channel) {
            if (!$channel->supports($notification)) {
                continue;
            }

            try {
                $channel->send($notification);
                $delivered[] = $channel->getName();
            } catch (\Throwable $e) {
                $failed[$channel->getName()] = $e->getMessage();

                $this->logger->error('Notification channel failed.', [
                    'channel' => $channel->getName(),
                    'notification' => $notification->getType(),
                    'subject_type' => $notification->getSubjectType(),
                    'subject_id' => $notification->getSubjectId(),
                    'exception' => $e,
                ]);
            }
        }

        return new DispatchReport($delivered, $failed);
    }
}
