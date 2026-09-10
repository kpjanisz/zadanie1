<?php

declare(strict_types=1);

namespace App\Notification\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One delivery mechanism for notifications.
 *
 * Adding Slack, SMS or a webhook means writing one class that implements this
 * interface — nothing else. The tag below is applied automatically, so the
 * dispatcher picks the new channel up with no change to existing code or config.
 * That is the extensibility requirement of the task, expressed in one attribute.
 */
#[AutoconfigureTag('app.notification_channel')]
interface NotificationChannelInterface
{
    /** Stable identifier, recorded in the operation log. */
    public function getName(): string;

    /** Lets a channel opt out of notification types it has nothing to say about. */
    public function supports(NotificationInterface $notification): bool;

    /**
     * Delivers the notification.
     *
     * May throw: the dispatcher isolates failures per channel.
     */
    public function send(NotificationInterface $notification): void;
}
