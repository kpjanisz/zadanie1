<?php

declare(strict_types=1);

namespace App\Notification\Dto;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Notification\Entity\OperationLog;
use App\Notification\State\OperationLogCollectionProvider;
use App\Notification\State\OperationLogProvider;

/**
 * The audit trail written by the notification fan-out, exposed read-only.
 *
 * There are deliberately no write operations: the log is append-only and the
 * only thing allowed to append to it is DatabaseLogChannel. Exposing it makes
 * the effect of a save observable over the API instead of only in SQL.
 */
#[ApiResource(
    shortName: 'OperationLog',
    operations: [
        new GetCollection(
            provider: OperationLogCollectionProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Get(
            provider: OperationLogProvider::class,
            security: "is_granted('ROLE_ADMIN')",
        ),
    ],
    order: ['createdAt' => 'DESC', 'id' => 'DESC'],
    stateOptions: new Options(entityClass: OperationLog::class),
)]
#[ApiFilter(SearchFilter::class, properties: [
    'channel' => 'exact',
    'action' => 'exact',
    'subjectType' => 'exact',
    'subjectId' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'createdAt'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
final readonly class OperationLogOutput
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,
        #[ApiProperty(example: 'database_log')]
        public string $channel,
        #[ApiProperty(example: 'created')]
        public string $action,
        #[ApiProperty(example: 'Product')]
        public string $subjectType,
        public int $subjectId,
        public array $payload,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
