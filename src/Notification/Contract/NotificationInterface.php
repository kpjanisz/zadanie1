<?php

declare(strict_types=1);

namespace App\Notification\Contract;

use App\Notification\Enum\OperationAction;

/**
 * What happened, in a channel-agnostic shape.
 *
 * Implementations are immutable value objects in Notification/Dto. They carry
 * data only: how it is rendered or stored is each channel's business.
 */
interface NotificationInterface
{
    /** Stable machine name, e.g. "product.changed". Used for routing and filtering. */
    public function getType(): string;

    public function getAction(): OperationAction;

    /** Short class name of the subject, e.g. "Product". */
    public function getSubjectType(): string;

    public function getSubjectId(): int;

    /** Translation key for a human-readable title; never a literal string. */
    public function getSubjectKey(): string;

    /**
     * Payload describing the subject. Must be JSON-serialisable, because the
     * database channel stores it verbatim. Never put secrets in here.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array;
}
