<?php

declare(strict_types=1);

namespace App\Shared\Mail;

/**
 * Base for every outgoing mail.
 *
 * A mail describes itself — recipients, subject key, template, data — and knows
 * nothing about transports. MailerService turns it into a message.
 */
abstract class AbstractMail
{
    /**
     * @return non-empty-list<string>
     */
    abstract public function getTo(): array;

    /**
     * Translation key, never a literal subject line.
     */
    abstract public function getSubject(): string;

    /**
     * @return array<string, mixed>
     */
    public function getSubjectParameters(): array
    {
        return [];
    }

    /**
     * Directory under templates/mail/, e.g. "NotificationMail".
     */
    abstract public function getTemplateName(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function getTemplateData(): array;
}
