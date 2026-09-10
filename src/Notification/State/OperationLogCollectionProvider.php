<?php

declare(strict_types=1);

namespace App\Notification\State;

use ApiPlatform\State\ProviderInterface;
use App\Notification\Dto\OperationLogOutput;
use App\Notification\Entity\OperationLog;
use App\Notification\Mapper\OperationLogMapper;
use App\Shared\State\MappedCollectionProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends MappedCollectionProvider<OperationLog, OperationLogOutput>
 */
final readonly class OperationLogCollectionProvider extends MappedCollectionProvider
{
    /**
     * @param ProviderInterface<OperationLog> $doctrineCollectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        ProviderInterface $doctrineCollectionProvider,
        private OperationLogMapper $mapper,
    ) {
        parent::__construct($doctrineCollectionProvider);
    }

    protected function mapToOutput(object $entity): OperationLogOutput
    {
        \assert($entity instanceof OperationLog);

        return $this->mapper->mapToOutput($entity);
    }
}
