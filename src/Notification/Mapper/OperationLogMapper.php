<?php

declare(strict_types=1);

namespace App\Notification\Mapper;

use App\Notification\Dto\OperationLogOutput;
use App\Notification\Entity\OperationLog;

final readonly class OperationLogMapper
{
    public function mapToOutput(OperationLog $log): OperationLogOutput
    {
        return new OperationLogOutput(
            id: $log->getId() ?? throw new \LogicException('Cannot map an unpersisted operation log.'),
            channel: $log->getChannel(),
            action: $log->getAction()->value,
            subjectType: $log->getSubjectType(),
            subjectId: $log->getSubjectId(),
            payload: $log->getPayload(),
            createdAt: $log->getCreatedAt(),
        );
    }
}
