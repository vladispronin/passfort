<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Vault;

use App\Entity\User;
use App\Entity\Vault;
use App\Repository\VaultRepository;
use App\Service\Vault\VaultService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

class VaultServiceTest extends TestCase
{
    private VaultService $service;
    private VaultRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(VaultRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new VaultService($this->repository, $this->em);
    }

    public function testCreate(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $vault = $this->service->create($user, 'Test Vault');

        $this->assertInstanceOf(Vault::class, $vault);
        $this->assertEquals('Test Vault', $vault->getName());
        $this->assertSame($user, $vault->getUser());
    }

    public function testCreateDefaultVault(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $vault = $this->service->createDefaultVault($user);

        $this->assertEquals('My Vault', $vault->getName());
    }

    public function testFindByUser(): void
    {
        $user = new User();
        $vaults = [new Vault(), new Vault()];

        $this->repository->expects($this->once())
            ->method('findByUser')
            ->with($user)
            ->willReturn($vaults);

        $result = $this->service->findByUser($user);
        $this->assertCount(2, $result);
    }

    public function testFindByIdAndUserSuccess(): void
    {
        $userId = Uuid::v4();
        $user = new User();
        $refUser = new \ReflectionProperty(User::class, 'id');
        $refUser->setValue($user, $userId);

        $vault = new Vault();
        $vault->setUser($user);

        $this->repository->expects($this->once())
            ->method('find')
            ->with('vault-id')
            ->willReturn($vault);

        $result = $this->service->findByIdAndUser('vault-id', $user);
        $this->assertSame($vault, $result);
    }

    public function testFindByIdAndUserNotFound(): void
    {
        $user = new User();

        $this->repository->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Vault not found');

        $this->service->findByIdAndUser('non-existent', $user);
    }

    public function testFindByIdAndUserAccessDenied(): void
    {
        // Два разных пользователя
        $userId1 = Uuid::v4();
        $user1 = new User();
        $refUser1 = new \ReflectionProperty(User::class, 'id');
        $refUser1->setValue($user1, $userId1);

        $userId2 = Uuid::v4();
        $user2 = new User();
        $refUser2 = new \ReflectionProperty(User::class, 'id');
        $refUser2->setValue($user2, $userId2);

        $vault = new Vault();
        $vault->setUser($user2); // хранилище принадлежит user2

        $this->repository->expects($this->once())
            ->method('find')
            ->willReturn($vault);

        $this->expectException(AccessDeniedException::class);

        // Запрашиваем от user1 — должен быть Access Denied
        $this->service->findByIdAndUser('vault-id', $user1);
    }

    public function testUpdate(): void
    {
        $vault = new Vault();
        $vault->setName('Old Name');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->update($vault, 'New Name');

        $this->assertEquals('New Name', $result->getName());
    }

    public function testDelete(): void
    {
        $vault = new Vault();

        $this->em->expects($this->once())->method('remove')->with($vault);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($vault);
    }
}
