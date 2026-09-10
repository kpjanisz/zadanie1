<?php

declare(strict_types=1);

namespace App\Notification\Service;

use App\Notification\Contract\NotificationInterface;
use App\Notification\Entity\OperationLog;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes the audit trail — the "log operacji" the task asks for.
 *
 * Deliberately NOT a notification channel. A channel runs after the write has
 * committed, which means its own INSERT is a second transaction: if that one
 * fails, the product exists and its audit row does not. For an audit trail that
 * silent gap is the worst possible failure, so the record is persisted inside
 * the caller's transaction instead and commits atomically with the subject.
 *
 * It only persists; flushing and committing belong to the transaction that owns
 * the write.
 */
final readonly class OperationLogRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public const string CHANNEL = 'audit';

    public function record(NotificationInterface $notification): OperationLog
    {
        $log = new OperationLog(
            channel: self::CHANNEL,
            action: $notification->getAction(),
            subjectType: $notification->getSubjectType(),
            subjectId: $notification->getSubjectId(),
            payload: $notification->getContext(),
        );

        $this->entityManager->persist($log);

        return $log;
    }
}
