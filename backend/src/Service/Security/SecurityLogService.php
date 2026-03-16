<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\SecurityLog;
use App\Entity\User;
use App\Message\SecurityLogMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\Request;

class SecurityLogService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    public function log(
        string $action,
        ?User $user = null,
        ?Request $request = null,
        array $metadata = [],
    ): void {
        // Асинхронная запись через Kafka
        $message = new SecurityLogMessage(
            action: $action,
            userId: $user?->getId()?->toRfc4122(),
            ipAddress: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent'),
            metadata: $metadata,
        );

        try {
            $this->bus->dispatch($message);
        } catch (\Throwable) {
            // Fallback: синхронная запись если Kafka недоступна
            $this->logSync($action, $user, $request, $metadata);
        }
    }

    private function logSync(
        string $action,
        ?User $user,
        ?Request $request,
        array $metadata,
    ): void {
        $log = new SecurityLog();
        $log->setAction($action);
        $log->setUser($user);
        $log->setIpAddress($request?->getClientIp());
        $log->setUserAgent($request?->headers->get('User-Agent'));
        $log->setMetadata($metadata);

        $this->em->persist($log);
        $this->em->flush();
    }
}
