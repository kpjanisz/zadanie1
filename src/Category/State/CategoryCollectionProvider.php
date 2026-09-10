<?php

declare(strict_types=1);

namespace App\Category\State;

use ApiPlatform\State\ProviderInterface;
use App\Category\Dto\CategoryOutput;
use App\Category\Entity\Category;
use App\Category\Mapper\CategoryMapper;
use App\Shared\State\MappedCollectionProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends MappedCollectionProvider<Category, CategoryOutput>
 */
final readonly class CategoryCollectionProvider extends MappedCollectionProvider
{
    /**
     * @param ProviderInterface<Category> $doctrineCollectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        ProviderInterface $doctrineCollectionProvider,
        private CategoryMapper $mapper,
    ) {
        parent::__construct($doctrineCollectionProvider);
    }

    protected function mapToOutput(object $entity): CategoryOutput
    {
        \assert($entity instanceof Category);

        return $this->mapper->mapToOutput($entity);
    }
}
