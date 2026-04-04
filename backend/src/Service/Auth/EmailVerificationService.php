<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Exception\EmailVerificationException;
use App\Message\EmailNotificationMessage;
use App\Repository\EmailVerificationTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

class EmailVerificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EmailVerificationTokenRepository $tokenRepository,
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $bus,
        #[Autowire(env: 'APP_URL')]
        private readonly string $appUrl,
    ) {}

    public function sendVerificationEmail(User $user): void
    {
        // Инвалидируем старые токены пользователя
        $this->tokenRepository->deleteByUser($user);

        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        $token = new EmailVerificationToken();
        $token->setUser($user);
        $token->setTokenHash($hash);
        $token->setExpiresAt(new \DateTimeImmutable('+24 hours'));

        $this->em->persist($token);
        $this->em->flush();

        $link = rtrim($this->appUrl, '/') . '/api/v1/auth/verify-email?token=' . $raw;

        $this->bus->dispatch(new EmailNotificationMessage(
            to: $user->getEmail(),
            subject: 'Подтвердите ваш email — PassFort',
            template: 'email_verification',
            context: [
                'verification_link' => $link,
                'expires_hours' => 24,
            ],
        ));
    }

    public function verifyToken(string $rawToken): User
    {
        $hash = hash('sha256', $rawToken);
        $token = $this->tokenRepository->findByTokenHash($hash);

        if ($token === null) {
            throw new EmailVerificationException('Invalid or expired token');
        }

        if ($token->isExpired()) {
            $this->em->remove($token);
            $this->em->flush();
            throw new EmailVerificationException('Token has expired');
        }

        $user = $token->getUser();
        $user->setIsEmailVerified(true);

        $this->em->remove($token);
        $this->em->flush();

        return $user;
    }

    public function resendVerification(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        // Не раскрываем факт существования email
        if ($user === null || $user->isEmailVerified()) {
            return;
        }

        $this->sendVerificationEmail($user);
    }
}
