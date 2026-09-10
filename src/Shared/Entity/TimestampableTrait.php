<?php

declare(strict_types=1);

namespace App\Shared\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Automatic creation/update timestamps.
 *
 * The using entity MUST carry #[ORM\HasLifecycleCallbacks], otherwise Doctrine
 * ignores the callbacks below and both columns stay unset.
 *
 * Timestamps are always UTC and are never accepted from a request payload.
 */
trait TimestampableTrait
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => 'UTC'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => 'UTC'])]
    private \DateTimeImmutable $updatedAt;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function initialiseTimestamps(): void
    {
        $now = self::now();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = self::now();
    }

    /**
     * Bumps updatedAt explicitly.
     *
     * Doctrine does not consider a changed ManyToMany collection a change to the
     * owning entity, so #[ORM\PreUpdate] does NOT fire when only a product's
     * categories are reassigned. Call this whenever a collection is the only edit;
     * the resulting field change is also what makes the UnitOfWork schedule the
     * UPDATE at all.
     */
    public function markUpdated(): void
    {
        $this->updatedAt = self::now();
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
