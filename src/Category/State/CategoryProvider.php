<?php

declare(strict_types=1);

namespace App\Category\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Category\Dto\CategoryOutput;
use App\Category\Exception\CategoryNotFoundException;
use App\Category\Http\CategoryEtag;
use App\Category\Mapper\CategoryMapper;
use App\Category\Repository\CategoryRepository;
use App\Shared\Http\PreconditionChecker;

/**
 * @implements ProviderInterface<CategoryOutput>
 */
final readonly class CategoryProvider implements ProviderInterface
{
    public function __construct(
        private CategoryRepository $repository,
        private CategoryMapper $mapper,
        private PreconditionChecker $preconditions,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CategoryOutput
    {
        $id = $uriVariables['id'] ?? null;

        // A non-numeric id is a missing resource, not a 500.
        if (!is_numeric($id)) {
            throw new CategoryNotFoundException(\is_scalar($id) ? (string) $id : '');
        }

        $category = $this->repository->find((int) $id)
            ?? throw new CategoryNotFoundException((int) $id);

        $this->preconditions->remember(CategoryEtag::for($category));

        return $this->mapper->mapToOutput($category);
    }
}
