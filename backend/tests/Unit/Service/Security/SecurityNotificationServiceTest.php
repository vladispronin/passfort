<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Entity\User;
use App\Message\EmailNotificationMessage;
use App\Service\Security\SecurityNotificationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SecurityNotificationServiceTest extends TestCase
{
    private SecurityNotificationService $service;
    private MessageBusInterface&MockObject $bus;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SecurityNotificationService($this->bus, $this->logger);
    }

    private function makeUser(string $email = 'user@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        return $user;
    }

    public function testNotifyNewLoginDispatchesMessage(): void
    {
        $user = $this->makeUser();
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '1.2.3.4']);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (EmailNotificationMessage $msg) use ($user) {
                return $msg->to === $user->getEmail()
                    && $msg->template === 'security_new_login'
                    && isset($msg->context['ip'], $msg->context['device'], $msg->context['datetime']);
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->notifyNewLogin($user, $request);
    }

    public function testNotifyPasswordChangedDispatchesMessage(): void
    {
        $user = $this->makeUser();
        $request = Request::create('/');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (EmailNotificationMessage $msg) use ($user) {
                return $msg->to === $user->getEmail()
                    && $msg->template === 'security_password_changed'
                    && isset($msg->context['ip'], $msg->context['datetime']);
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->notifyPasswordChanged($user, $request);
    }

    public function testNotifyAccountDeletedDispatchesMessage(): void
    {
        $email = 'deleted@example.com';
        $request = Request::create('/');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (EmailNotificationMessage $msg) use ($email) {
                return $msg->to === $email
                    && $msg->template === 'security_account_deleted'
                    && isset($msg->context['ip'], $msg->context['datetime']);
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->service->notifyAccountDeleted($email, $request);
    }

    public function testDispatchFailureLogsWarningAndDoesNotThrow(): void
    {
        $user = $this->makeUser();
        $request = Request::create('/');

        $this->bus->method('dispatch')
            ->willThrowException(new \RuntimeException('Kafka unavailable'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Failed to dispatch security notification email', $this->arrayHasKey('error'));

        // Исключение не должно пробрасываться наружу
        $this->service->notifyNewLogin($user, $request);
    }
}
