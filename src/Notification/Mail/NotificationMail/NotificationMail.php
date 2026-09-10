<?php

declare(strict_types=1);

namespace App\Notification\Mail\NotificationMail;

use App\Notification\Contract\NotificationInterface;
use App\Shared\Mail\AbstractMail;

/**
 * Generic notification e-mail: renders any NotificationInterface.
 *
 * Keeping it generic is what lets a new notification type reuse the e-mail
 * channel without touching it.
 */
final class NotificationMail extends AbstractMail
{
    public function __construct(
        private readonly string $recipient,
        private readonly NotificationInterface $notification,
    ) {
    }

    public function getTo(): array
    {
        return [$this->recipient];
    }

    public function getSubject(): string
    {
        return $this->notification->getSubjectKey();
    }

    public function getSubjectParameters(): array
    {
        return ['%subjectId%' => $this->notification->getSubjectId()];
    }

    public function getTemplateName(): string
    {
        return 'NotificationMail';
    }

    public function getTemplateData(): array
    {
        return [
            'type' => $this->notification->getType(),
            'action' => $this->notification->getAction(),
            'subjectType' => $this->notification->getSubjectType(),
            'subjectId' => $this->notification->getSubjectId(),
            'context' => $this->notification->getContext(),
        ];
    }
}
