<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Double;

use App\Notification\Contract\NotificationChannelInterface;
use App\Notification\Contract\NotificationInterface;

/**
 * A channel that only remembers what it was handed.
 *
 * A named class rather than an anonymous one so tests can read $received
 * through a declared type.
 */
final class RecordingChannel implements NotificationChannelInterface
{
    /** @var list<NotificationInterface> */
    public array $received = [];

    public function __construct(
        private readonly string $name = 'recorder',
        private readonly bool $supports = true,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function supports(NotificationInterface $notification): bool
    {
        return $this->supports;
    }

    public function send(NotificationInterface $notification): void
    {
        $this->received[] = $notification;
    }

    public function last(): ?NotificationInterface
    {
        return $this->received[array_key_last($this->received)] ?? null;
    }
}
