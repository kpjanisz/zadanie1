<?php

declare(strict_types=1);

namespace App\Category\Repository;

use App\Category\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findOneByCode(string $code): ?Category
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * True when another category already uses this code.
     *
     * $exceptId lets an update keep its own code without tripping the check.
     */
    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('1')
            ->andWhere('c.code = :code')
            ->setParameter('code', $code)
            ->setMaxResults(1);

        if (null !== $exceptId) {
            $qb->andWhere('c.id != :exceptId')
                ->setParameter('exceptId', $exceptId);
        }

        return [] !== $qb->getQuery()->getScalarResult();
    }

    /**
     * Resolves many ids in one query — the processor must never loop over find().
     *
     * @param list<int> $ids
     *
     * @return list<Category>
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Category> $result */
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', array_values(array_unique($ids)))
            ->getQuery()
            ->getResult();

        return $result;
    }
}
