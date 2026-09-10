<?php

declare(strict_types=1);

namespace App\Notification\Entity;

use App\Notification\Enum\OperationAction;
use App\Notification\Repository\OperationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Append-only audit trail of dispatched notifications.
 *
 * This is the "log operacji" the task asks for: queryable, unlike a text log file
 * (a PSR-3 channel writes there too, see PsrLoggerChannel).
 */
#[ORM\Entity(repositoryClass: OperationLogRepository::class)]
#[ORM\Table(name: 'operation_log')]
#[ORM\Index(name: 'idx_operation_log_subject', columns: ['subject_type', 'subject_id'])]
#[ORM\Index(name: 'idx_operation_log_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class OperationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Name of the notification channel that produced this entry. */
    #[ORM\Column(length: 32)]
    private string $channel;

    #[ORM\Column(length: 16, enumType: OperationAction::class)]
    private OperationAction $action;

    /** Short class name of the subject, e.g. "Product". */
    #[ORM\Column(length: 64)]
    private string $subjectType;

    #[ORM\Column]
    private int $subjectId;

    /**
     * Snapshot of what was notified about. Never store secrets here.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    /**
     * Only createdAt: the table is append-only, so TimestampableTrait would add
     * an updatedAt column that can never change.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => 'UTC'])]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $channel,
        OperationAction $action,
        string $subjectType,
        int $subjectId,
        array $payload = [],
    ) {
        $this->channel = $channel;
        $this->action = $action;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->payload = $payload;
    }

    #[ORM\PrePersist]
    public function initialiseCreatedAt(): void
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getAction(): OperationAction
    {
        return $this->action;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
