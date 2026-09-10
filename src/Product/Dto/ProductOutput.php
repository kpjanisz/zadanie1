<?php

declare(strict_types=1);

namespace App\Product\Dto;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Product\Entity\Product;
use App\Product\State\ProductCollectionProvider;
use App\Product\State\ProductProcessor;
use App\Product\State\ProductProvider;

#[ApiResource(
    shortName: 'Product',
    operations: [
        new GetCollection(
            provider: ProductCollectionProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Get(
            provider: ProductProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Post(
            security: "is_granted('ROLE_ADMIN')",
            input: CreateProductInput::class,
            processor: ProductProcessor::class,
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            input: UpdateProductInput::class,
            provider: ProductProvider::class,
            processor: ProductProcessor::class,
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            provider: ProductProvider::class,
            processor: ProductProcessor::class,
        ),
    ],
    stateOptions: new Options(entityClass: Product::class),
)]
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial', 'categories.code' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'name', 'price', 'createdAt'])]
#[ApiFilter(RangeFilter::class, properties: ['price'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
final readonly class ProductOutput
{
    /**
     * @param list<ProductCategoryOutput> $categories
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,
        public string $name,
        /**
         * Concurrency token. A client that reads this value and sends it back as
         * `If-Match: "<id>-<version>-<hash>"` is told with 412 when somebody
         * else got there first, instead of silently overwriting their change.
         */
        public int $version,
        /** Decimal string, never a float — see Product::$price. */
        #[ApiProperty(example: '1299.00')]
        public string $price,
        /**
         * In JSON-LD each entry also carries a blank-node @id under
         * /api/.well-known/genid/, and it is REGENERATED AT RANDOM on every
         * request. The body therefore differs every time, so the ETag never
         * matches and conditional requests on this representation can never hit.
         * Plain application/json is unaffected and does revalidate.
         *
         * Not fixable from here: ApiPlatform\JsonLd\Serializer\ItemNormalizer
         * forces `output.gen_id` to true for a nested collection before the
         * property metadata is read, so neither ApiProperty(genId: false) nor
         * normalizationContext['gen_id'] can override it. Both were tried and
         * removed rather than left as configuration that does nothing.
         */
        public array $categories,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
