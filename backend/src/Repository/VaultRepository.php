<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Vault;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VaultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vault::class);
    }

    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }
}
