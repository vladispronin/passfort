<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Exception\EmailChangeException;
use App\Message\EmailNotificationMessage;
use App\Repository\UserRepository;
use App\Service\User\EmailChangeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class EmailChangeServiceTest extends TestCase
{
    private EmailChangeService $service;
    private EntityManagerInterface&MockObject $em;
    private UserRepository&MockObject $userRepository;
    private MessageBusInterface&MockObject $bus;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        $this->service = new EmailChangeService(
            $this->em,
            $this->userRepository,
            $this->bus,
            'http://localhost:19080',
        );
    }

    private function makeUser(string $email = 'user@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setSalt('salt');
        $user->setKdfParams([]);
        $user->setMasterPasswordHash(str_repeat('a', 64));
        return $user;
    }

    // -------------------------------------------------------------------------
    // requestEmailChange
    // -------------------------------------------------------------------------

    public function testRequestEmailChangeSuccess(): void
    {
        $user = $this->makeUser('old@example.com');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('new@example.com')
            ->willReturn(null);

        $this->em->expects($this->once())->method('flush');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (EmailNotificationMessage $msg) {
                return $msg->to === 'new@example.com'
                    && $msg->template === 'email_change_confirmation'
                    && str_contains($msg->context['confirmation_link'] ?? '', '/email-change/confirm?token=')
                    && ($msg->context['new_email'] ?? '') === 'new@example.com'
                    && ($msg->context['expires_hours'] ?? 0) === 1;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->requestEmailChange($user, 'new@example.com');

        $this->assertEquals('new@example.com', $user->getPendingEmail());
        $this->assertNotNull($user->getEmailChangeTokenHash());
        $this->assertNotNull($user->getEmailChangeTokenExpiresAt());
    }

    public function testRequestEmailChangeNormalizesEmail(): void
    {
        $user = $this->makeUser('old@example.com');

        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->em->method('flush');
        $this->bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->service->requestEmailChange($user, '  NEW@EXAMPLE.COM  ');

        $this->assertEquals('new@example.com', $user->getPendingEmail());
    }

    public function testRequestEmailChangeSameEmailThrows(): void
    {
        $user = $this->makeUser('same@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('New email must differ from current email');

        $this->service->requestEmailChange($user, 'same@example.com');
    }

    public function testRequestEmailChangeEmailInUseByOtherUserThrows(): void
    {
        $user = $this->makeUser('user@example.com');

        $otherUser = $this->makeUser('taken@example.com');
        // Убеждаемся что другой пользователь имеет другой UUID через reflection
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($otherUser, Uuid::v4());

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('taken@example.com')
            ->willReturn($otherUser);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already in use');

        $this->service->requestEmailChange($user, 'taken@example.com');
    }

    // -------------------------------------------------------------------------
    // confirmEmailChange
    // -------------------------------------------------------------------------

    public function testConfirmEmailChangeSuccess(): void
    {
        $user = $this->makeUser('old@example.com');
        $user->setPendingEmail('new@example.com')
             ->setEmailChangeTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $user->setEmailChangeTokenHash($hash);

        $this->userRepository->expects($this->once())
            ->method('findByEmailChangeTokenHash')
            ->with($hash)
            ->willReturn($user);

        // Race condition check: нет конфликта
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('new@example.com')
            ->willReturn(null);

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->confirmEmailChange($rawToken);

        $this->assertSame($user, $result['user']);
        $this->assertEquals('old@example.com', $result['oldEmail']);
        $this->assertEquals('new@example.com', $user->getEmail());
        $this->assertTrue($user->isEmailVerified());
        $this->assertNull($user->getPendingEmail());
        $this->assertNull($user->getEmailChangeTokenHash());
        $this->assertNull($user->getEmailChangeTokenExpiresAt());
    }

    public function testConfirmEmailChangeInvalidTokenThrows(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmailChangeTokenHash')
            ->willReturn(null);

        $this->expectException(EmailChangeException::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $this->service->confirmEmailChange('invalid_token');
    }

    public function testConfirmEmailChangeExpiredTokenClearsFieldsAndThrows(): void
    {
        $user = $this->makeUser('old@example.com');
        $user->setPendingEmail('new@example.com')
             ->setEmailChangeTokenHash('somehash')
             ->setEmailChangeTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $user->setEmailChangeTokenHash($hash);

        $this->userRepository->expects($this->once())
            ->method('findByEmailChangeTokenHash')
            ->willReturn($user);

        $this->em->expects($this->once())->method('flush');

        $this->expectException(EmailChangeException::class);
        $this->expectExceptionMessage('Token has expired');

        $this->service->confirmEmailChange($rawToken);

        $this->assertNull($user->getPendingEmail());
        $this->assertNull($user->getEmailChangeTokenHash());
        $this->assertNull($user->getEmailChangeTokenExpiresAt());
    }

    public function testConfirmEmailChangeRaceConditionThrows(): void
    {
        $user = $this->makeUser('old@example.com');
        $user->setPendingEmail('taken@example.com')
             ->setEmailChangeTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $user->setEmailChangeTokenHash($hash);

        $otherUser = $this->makeUser('taken@example.com');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($otherUser, Uuid::v4());

        $this->userRepository->expects($this->once())
            ->method('findByEmailChangeTokenHash')
            ->willReturn($user);

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('taken@example.com')
            ->willReturn($otherUser);

        $this->em->expects($this->once())->method('flush');

        $this->expectException(EmailChangeException::class);
        $this->expectExceptionMessage('Email address is no longer available');

        $this->service->confirmEmailChange($rawToken);

        $this->assertNull($user->getPendingEmail());
        $this->assertNull($user->getEmailChangeTokenHash());
    }
}
