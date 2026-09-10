<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\Contract\NotificationChannelInterface;
use App\Notification\Contract\NotificationInterface;
use App\Notification\Mail\NotificationMail\NotificationMail;
use App\Shared\Service\MailerService;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sends the notification by e-mail.
 *
 * Deliberately generic: it renders whatever getSubjectKey() and getContext()
 * provide, so a new notification type needs no change here. Locally the transport
 * is Mailpit, so the mail is really delivered and inspectable without leaving the
 * machine.
 */
/**
 * Runs last: the only channel that makes a network call.
 */
#[AsTaggedItem(priority: 0)]
final readonly class EmailChannel implements NotificationChannelInterface
{
    public const string NAME = 'email';

    public function __construct(
        private MailerService $mailerService,
        #[Autowire('%env(NOTIFICATION_RECIPIENT)%')]
        private string $recipient,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function supports(NotificationInterface $notification): bool
    {
        // Nothing to send to nobody; an unconfigured recipient disables the
        // channel instead of throwing on every write.
        return '' !== trim($this->recipient);
    }

    public function send(NotificationInterface $notification): void
    {
        $this->mailerService->send(new NotificationMail($this->recipient, $notification));
    }
}
