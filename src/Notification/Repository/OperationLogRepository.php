<?php

declare(strict_types=1);

namespace App\Notification\Repository;

use App\Notification\Entity\OperationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OperationLog>
 */
class OperationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperationLog::class);
    }

    /**
     * Audit trail for one subject, newest first.
     *
     * @return list<OperationLog>
     */
    public function findForSubject(string $subjectType, int $subjectId, int $limit = 50): array
    {
        /** @var list<OperationLog> $result */
        $result = $this->createQueryBuilder('l')
            ->andWhere('l.subjectType = :type')
            ->andWhere('l.subjectId = :id')
            ->setParameter('type', $subjectType)
            ->setParameter('id', $subjectId)
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
