<?php

declare(strict_types=1);

namespace App\Service\Vault;

use App\Entity\User;
use App\Entity\Vault;
use App\Repository\VaultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class VaultService
{
    public function __construct(
        private readonly VaultRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function createDefaultVault(User $user): Vault
    {
        return $this->create($user, 'My Vault');
    }

    public function create(User $user, string $name): Vault
    {
        $vault = new Vault();
        $vault->setUser($user);
        $vault->setName($name);

        $this->em->persist($vault);
        $this->em->flush();

        return $vault;
    }

    public function findByUser(User $user): array
    {
        return $this->repository->findByUser($user);
    }

    public function findByIdAndUser(string $id, User $user): Vault
    {
        $vault = $this->repository->find($id);

        if ($vault === null) {
            throw new \RuntimeException('Vault not found');
        }

        if ($vault->getUser()->getId()?->toRfc4122() !== $user->getId()?->toRfc4122()) {
            throw new AccessDeniedException('Access denied');
        }

        return $vault;
    }

    public function update(Vault $vault, string $name): Vault
    {
        $vault->setName($name);
        $this->em->flush();
        return $vault;
    }

    public function delete(Vault $vault): void
    {
        $this->em->remove($vault);
        $this->em->flush();
    }
}
