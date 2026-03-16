<?php

declare(strict_types=1);

namespace App\Message\Handler;

use App\Entity\SecurityLog;
use App\Message\SecurityLogMessage;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SecurityLogHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
    ) {}

    public function __invoke(SecurityLogMessage $message): void
    {
        $log = new SecurityLog();
        $log->setAction($message->action);
        $log->setIpAddress($message->ipAddress);
        $log->setUserAgent($message->userAgent);
        $log->setMetadata($message->metadata ?: null);

        if ($message->userId !== null) {
            $user = $this->userRepository->find($message->userId);
            $log->setUser($user);
        }

        $this->em->persist($log);
        $this->em->flush();
    }
}
