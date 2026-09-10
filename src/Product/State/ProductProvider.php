<?php

declare(strict_types=1);

namespace App\Product\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Product\Dto\ProductOutput;
use App\Product\Exception\ProductNotFoundException;
use App\Product\Http\ProductEtag;
use App\Product\Mapper\ProductMapper;
use App\Product\Repository\ProductRepository;
use App\Shared\Http\PreconditionChecker;

/**
 * @implements ProviderInterface<ProductOutput>
 */
final readonly class ProductProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $repository,
        private ProductMapper $mapper,
        private PreconditionChecker $preconditions,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProductOutput
    {
        $id = $uriVariables['id'] ?? null;

        if (!is_numeric($id)) {
            throw new ProductNotFoundException(\is_scalar($id) ? (string) $id : '');
        }

        // Categories are always mapped, so they are fetched in the same query.
        $product = $this->repository->findOneWithCategories((int) $id)
            ?? throw new ProductNotFoundException((int) $id);

        // Stashed for the response listener and for the If-Match check: both
        // need the validator of the state as it is right now.
        $this->preconditions->remember(ProductEtag::for($product));

        return $this->mapper->mapToOutput($product);
    }
}
