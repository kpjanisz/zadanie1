<?php

declare(strict_types=1);

namespace App\Product\Repository;

use App\Product\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Loads a product with its categories in a single query.
     *
     * The mapper always reads getCategories(), so a plain find() would issue a
     * second SELECT per product — the N+1 this project treats as a defect.
     */
    public function findOneWithCategories(int $id): ?Product
    {
        return $this->createQueryBuilder('p')
            ->addSelect('c')
            ->leftJoin('p.categories', 'c')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * How many products reference a category. Used to refuse deleting a category
     * that is still in use, which would otherwise leave products with none.
     */
    public function countByCategory(int $categoryId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.categories', 'c')
            ->andWhere('c.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
