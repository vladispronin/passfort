<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Vault;
use App\Entity\VaultItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
}
