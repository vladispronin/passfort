<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Vault;
use App\Entity\VaultItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

class VaultItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VaultItem::class);
    }

    public function findByVault(Vault $vault): array
    {
        return $this->findBy(['vault' => $vault]);
    }

    public function findFavoritesByVault(Vault $vault): array
    {
        return $this->findBy(['vault' => $vault, 'isFavorite' => true]);
    }

    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('vi')
            ->join('vi.vault', 'v')
            ->where('v.user = :user')
            ->setParameter('user', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getResult();
    }
}
