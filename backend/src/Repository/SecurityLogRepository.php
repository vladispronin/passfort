<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SecurityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

class SecurityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityLog::class);
    }

    /**
     * @return SecurityLog[]
     */
    public function findByUserPaginated(User $user, int $page, int $limit): array
    {
        return $this->createQueryBuilder('sl')
            ->where('sl.user = :user')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->orderBy('sl.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('sl')
            ->select('COUNT(sl.id)')
            ->where('sl.user = :user')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
