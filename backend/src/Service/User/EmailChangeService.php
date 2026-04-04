<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Exception\EmailChangeException;
use App\Message\EmailNotificationMessage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

class EmailChangeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly MessageBusInterface $bus,
        #[Autowire(env: 'APP_URL')]
        private readonly string $appUrl,
    ) {}

    public function requestEmailChange(User $user, string $newEmail): void
    {
        $newEmail = strtolower(trim($newEmail));

        if ($user->getEmail() === $newEmail) {
            throw new \InvalidArgumentException('New email must differ from current email');
        }

        $conflict = $this->userRepository->findByEmail($newEmail);
        if ($conflict !== null && !$conflict->getId()->equals($user->getId())) {
            throw new \InvalidArgumentException('Email already in use');
        }

        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        $user->setPendingEmail($newEmail)
             ->setEmailChangeTokenHash($hash)
             ->setEmailChangeTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->em->flush();

        $link = rtrim($this->appUrl, '/') . '/email-change/confirm?token=' . $raw;

        $this->bus->dispatch(new EmailNotificationMessage(
            to: $newEmail,
            subject: 'Подтвердите смену email — PassFort',
            template: 'email_change_confirmation',
            context: [
                'confirmation_link' => $link,
                'new_email'         => $newEmail,
                'expires_hours'     => 1,
            ],
        ));
    }

    /**
     * @return array{user: User, oldEmail: string}
     */
    public function confirmEmailChange(string $rawToken): array
    {
        $hash = hash('sha256', $rawToken);
        $user = $this->userRepository->findByEmailChangeTokenHash($hash);

        if ($user === null) {
            throw new EmailChangeException('Invalid or expired token');
        }

        if ($user->getEmailChangeTokenExpiresAt() < new \DateTimeImmutable()) {
            $user->setPendingEmail(null)
                 ->setEmailChangeTokenHash(null)
                 ->setEmailChangeTokenExpiresAt(null);
            $this->em->flush();
            throw new EmailChangeException('Token has expired');
        }

        $pendingEmail = $user->getPendingEmail();

        // Защита от race condition: повторный конфликт-чек
        $conflict = $this->userRepository->findByEmail($pendingEmail);
        if ($conflict !== null && !$conflict->getId()->equals($user->getId())) {
            $user->setPendingEmail(null)
                 ->setEmailChangeTokenHash(null)
                 ->setEmailChangeTokenExpiresAt(null);
            $this->em->flush();
            throw new EmailChangeException('Email address is no longer available');
        }

        $oldEmail = $user->getEmail();

        $user->setEmail($pendingEmail)
             ->setIsEmailVerified(true)
             ->setPendingEmail(null)
             ->setEmailChangeTokenHash(null)
             ->setEmailChangeTokenExpiresAt(null);

        $this->em->flush();

        return ['user' => $user, 'oldEmail' => $oldEmail];
    }
}
