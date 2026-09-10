<?php

declare(strict_types=1);

namespace App\Notification\Enum;

/**
 * Which write produced a notification. Backed enum rather than a loose string so
 * the value set is closed and refactorable.
 */
enum OperationAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    public function translationKey(): string
    {
        return \sprintf('notification.action.%s', $this->value);
    }
}
