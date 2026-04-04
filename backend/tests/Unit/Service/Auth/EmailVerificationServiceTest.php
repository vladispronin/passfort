<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Exception\EmailVerificationException;
use App\Message\EmailNotificationMessage;
use App\Repository\UserRepository;
use App\Service\Auth\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class EmailVerificationServiceTest extends TestCase
{
    private EmailVerificationService $service;
    private EntityManagerInterface&MockObject $em;
    private UserRepository&MockObject $userRepository;
    private MessageBusInterface&MockObject $bus;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        $this->service = new EmailVerificationService(
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

    public function testSendVerificationEmailSetsTokenFieldsAndFlushes(): void
    {
        $user = $this->makeUser();

        $this->em->expects($this->once())->method('flush');
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(EmailNotificationMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->sendVerificationEmail($user);

        $this->assertNotNull($user->getVerificationTokenHash());
        $this->assertNotNull($user->getVerificationTokenExpiresAt());
        $this->assertGreaterThan(new \DateTimeImmutable(), $user->getVerificationTokenExpiresAt());
    }

    public function testSendVerificationEmailDispatchesCorrectMessage(): void
    {
        $user = $this->makeUser('test@example.com');

        $this->em->method('flush');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (EmailNotificationMessage $msg) {
                return $msg->to === 'test@example.com'
                    && $msg->template === 'email_verification'
                    && str_contains($msg->context['verification_link'] ?? '', '/api/v1/auth/verify-email?token=')
                    && ($msg->context['expires_hours'] ?? 0) === 24;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->sendVerificationEmail($user);
    }

    public function testVerifyTokenSuccess(): void
    {
        $user = $this->makeUser();
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $user->setVerificationTokenHash($hash)
             ->setVerificationTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->userRepository->expects($this->once())
            ->method('findByVerificationTokenHash')
            ->with($hash)
            ->willReturn($user);

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->verifyToken($rawToken);

        $this->assertSame($user, $result);
        $this->assertTrue($user->isEmailVerified());
        $this->assertNull($user->getVerificationTokenHash());
        $this->assertNull($user->getVerificationTokenExpiresAt());
    }

    public function testVerifyTokenInvalidHash(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByVerificationTokenHash')
            ->willReturn(null);

        $this->expectException(EmailVerificationException::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $this->service->verifyToken('invalid_token');
    }

    public function testVerifyTokenExpiredClearsFieldsAndThrows(): void
    {
        $user = $this->makeUser();
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $user->setVerificationTokenHash($hash)
             ->setVerificationTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        $this->userRepository->expects($this->once())
            ->method('findByVerificationTokenHash')
            ->with($hash)
            ->willReturn($user);

        $this->em->expects($this->once())->method('flush');

        $this->expectException(EmailVerificationException::class);
        $this->expectExceptionMessage('Token has expired');

        $this->service->verifyToken($rawToken);

        $this->assertNull($user->getVerificationTokenHash());
        $this->assertNull($user->getVerificationTokenExpiresAt());
    }

    public function testResendVerificationSilentForNonExistentEmail(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->bus->expects($this->never())->method('dispatch');
        $this->em->expects($this->never())->method('flush');

        $this->service->resendVerification('unknown@example.com');
    }

    public function testResendVerificationSilentForAlreadyVerified(): void
    {
        $user = $this->makeUser();
        $user->setIsEmailVerified(true);

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($user);

        $this->bus->expects($this->never())->method('dispatch');

        $this->service->resendVerification('user@example.com');
    }

    public function testResendVerificationSendsEmailForUnverifiedUser(): void
    {
        $user = $this->makeUser('user@example.com');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($user);

        $this->em->method('flush');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->resendVerification('user@example.com');
    }
}
