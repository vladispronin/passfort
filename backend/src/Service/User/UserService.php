<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function updateEmail(User $user, string $email): User
    {
        $existing = $this->repository->findByEmail($email);
        if ($existing !== null && $existing->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('Email already in use');
        }

        $user->setEmail($email);
        $this->em->flush();
        return $user;
    }

    public function delete(User $user): void
    {
        $this->em->remove($user);
        $this->em->flush();
    }
}
