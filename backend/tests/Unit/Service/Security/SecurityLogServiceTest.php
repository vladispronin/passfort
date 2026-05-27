<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Entity\User;
use App\Enum\SecurityLogAction;
use App\Message\SecurityLogMessage;
use App\Service\Security\SecurityLogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SecurityLogServiceTest extends TestCase
{
    private SecurityLogService $service;
    private EntityManagerInterface&MockObject $em;
    private MessageBusInterface&MockObject $bus;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->service = new SecurityLogService($this->em, $this->bus);
    }

    public function testLogDispatchesMessage(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $request = Request::create('/');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SecurityLogMessage::class))
            ->willReturn(new Envelope(new SecurityLogMessage(action: SecurityLogAction::USER_LOGIN)));

        $this->service->log(SecurityLogAction::USER_LOGIN, $user, $request);
    }

    public function testLogWithoutUserAndRequest(): void
    {
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new SecurityLogMessage(action: SecurityLogAction::USER_LOGIN)));

        // Не должно бросать исключений
        $this->service->log(SecurityLogAction::USER_LOGIN);
    }

    public function testLogFallsBackToSyncWhenBusFails(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $request = Request::create('/');

        // Kafka недоступна — bus бросает исключение
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Kafka unavailable'));

        // Должен произойти fallback — синхронная запись через EM
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->log(SecurityLogAction::USER_LOGIN, $user, $request, ['extra' => 'data']);
    }

    public function testLogWithMetadata(): void
    {
        $metadata = ['email' => 'user@example.com', 'ip' => '127.0.0.1'];

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SecurityLogMessage $msg) use ($metadata) {
                return $msg->metadata === $metadata && $msg->action === SecurityLogAction::USER_LOGIN_FAILED;
            }))
            ->willReturn(new Envelope(new SecurityLogMessage(action: SecurityLogAction::USER_LOGIN_FAILED)));

        $this->service->log(SecurityLogAction::USER_LOGIN_FAILED, null, null, $metadata);
    }
}
