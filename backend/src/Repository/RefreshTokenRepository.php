<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findByTokenHash(string $tokenHash): ?RefreshToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function deleteByUser(User $user): void
    {
        $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.user = :user')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->execute();
    }

    /**
     * @return RefreshToken[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.user = :user')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('rt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByIdAndUser(string $id, User $user): ?RefreshToken
    {
        try {
            $uuid = \Symfony\Component\Uid\Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->createQueryBuilder('rt')
            ->where('rt.id = :id')
            ->andWhere('rt.user = :user')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
