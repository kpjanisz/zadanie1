<?php

declare(strict_types=1);

namespace App\Notification\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Notification\Dto\OperationLogOutput;
use App\Notification\Exception\OperationLogNotFoundException;
use App\Notification\Mapper\OperationLogMapper;
use App\Notification\Repository\OperationLogRepository;

/**
 * @implements ProviderInterface<OperationLogOutput>
 */
final readonly class OperationLogProvider implements ProviderInterface
{
    public function __construct(
        private OperationLogRepository $repository,
        private OperationLogMapper $mapper,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OperationLogOutput
    {
        $id = $uriVariables['id'] ?? null;

        if (!is_numeric($id)) {
            throw new OperationLogNotFoundException(\is_scalar($id) ? (string) $id : '');
        }

        $log = $this->repository->find((int) $id)
            ?? throw new OperationLogNotFoundException((int) $id);

        return $this->mapper->mapToOutput($log);
    }
}
