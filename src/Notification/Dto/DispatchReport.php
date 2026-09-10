<?php

declare(strict_types=1);

namespace App\Notification\Dto;

/**
 * Outcome of one fan-out. Returned rather than logged-and-forgotten so callers
 * and tests can assert on what actually happened.
 */
final readonly class DispatchReport
{
    /**
     * @param list<string> $delivered channel names that accepted the notification
     * @param array<string, string> $failed channel name => failure message
     */
    public function __construct(
        public array $delivered = [],
        public array $failed = [],
    ) {
    }

    public function hasFailures(): bool
    {
        return [] !== $this->failed;
    }

    public function deliveredCount(): int
    {
        return \count($this->delivered);
    }
}
