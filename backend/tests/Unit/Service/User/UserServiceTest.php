<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $service;
    private UserRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new UserService($this->repository, $this->em);
    }

    public function testUpdateEmailSuccess(): void
    {
        $user = new User();
        $user->setEmail('old@example.com');

        // Email не занят другим пользователем
        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with('new@example.com')
            ->willReturn(null);

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateEmail($user, 'new@example.com');

        $this->assertEquals('new@example.com', $result->getEmail());
    }

    public function testUpdateEmailSameUser(): void
    {
        // Пользователь меняет email на тот же самый (уже занят им)
        $user = new User();
        $user->setEmail('same@example.com');

        // Возвращаем того же пользователя — это не конфликт
        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with('same@example.com')
            ->willReturn($user);

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateEmail($user, 'same@example.com');
        $this->assertEquals('same@example.com', $result->getEmail());
    }

    public function testUpdateEmailThrowsWhenEmailTaken(): void
    {
        // Назначаем разные UUID, чтобы условие getId() !== getId() сработало корректно
        $userId1 = \Symfony\Component\Uid\Uuid::v4();
        $user = new User();
        $user->setEmail('original@example.com');
        $refUser = new \ReflectionProperty(User::class, 'id');
        $refUser->setValue($user, $userId1);

        $userId2 = \Symfony\Component\Uid\Uuid::v4();
        $anotherUser = new User();
        $anotherUser->setEmail('taken@example.com');
        $refAnother = new \ReflectionProperty(User::class, 'id');
        $refAnother->setValue($anotherUser, $userId2);

        $this->repository->expects($this->once())
            ->method('findByEmail')
            ->with('taken@example.com')
            ->willReturn($anotherUser);

        $this->em->expects($this->never())->method('flush');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already in use');

        $this->service->updateEmail($user, 'taken@example.com');
    }

    public function testDelete(): void
    {
        $user = new User();

        $this->em->expects($this->once())->method('remove')->with($user);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($user);
    }
}
