<?php

declare(strict_types=1);

namespace App\Category\Dto;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
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
use App\Category\Entity\Category;
use App\Category\State\CategoryCollectionProvider;
use App\Category\State\CategoryProcessor;
use App\Category\State\CategoryProvider;

/**
 * Public representation of a category. The Doctrine entity is never exposed.
 *
 * stateOptions binds the resource to the entity so Doctrine filters and
 * pagination work on the collection operation.
 */
#[ApiResource(
    shortName: 'Category',
    operations: [
        new GetCollection(
            provider: CategoryCollectionProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Get(
            provider: CategoryProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Post(
            security: "is_granted('ROLE_ADMIN')",
            input: CreateCategoryInput::class,
            processor: CategoryProcessor::class,
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            input: UpdateCategoryInput::class,
            provider: CategoryProvider::class,
            processor: CategoryProcessor::class,
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            provider: CategoryProvider::class,
            processor: CategoryProcessor::class,
        ),
    ],
    stateOptions: new Options(entityClass: Category::class),
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'code', 'createdAt'])]
final readonly class CategoryOutput
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,
        public string $code,
        /** Concurrency token; send it back as If-Match to make a write conditional. */
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
