<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Message\EmailNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

class SecurityNotificationService
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Уведомление о новом входе в аккаунт (новая сессия).
     */
    public function notifyNewLogin(User $user, Request $request): void
    {
        $this->dispatch(new EmailNotificationMessage(
            to: $user->getEmail(),
            subject: 'Новый вход в PassFort',
            template: 'security_new_login',
            context: [
                'ip'       => $request->getClientIp() ?? 'unknown',
                'device'   => $request->headers->get('User-Agent', 'unknown'),
                'datetime' => (new \DateTimeImmutable())->format('d.m.Y H:i:s \U\T\C'),
            ],
        ));
    }

    /**
     * Уведомление о смене мастер-пароля.
     */
    public function notifyPasswordChanged(User $user, Request $request): void
    {
        $this->dispatch(new EmailNotificationMessage(
            to: $user->getEmail(),
            subject: 'Мастер-пароль PassFort изменён',
            template: 'security_password_changed',
            context: [
                'ip'       => $request->getClientIp() ?? 'unknown',
                'datetime' => (new \DateTimeImmutable())->format('d.m.Y H:i:s \U\T\C'),
            ],
        ));
    }

    /**
     * Уведомление о смене email — отправляется на СТАРЫЙ адрес после подтверждения.
     * oldEmail передаётся явно, так как User::$email к моменту вызова уже обновлён.
     */
    public function notifyEmailChanged(string $oldEmail, string $newEmail, Request $request): void
    {
        $this->dispatch(new EmailNotificationMessage(
            to: $oldEmail,
            subject: 'Email аккаунта PassFort изменён',
            template: 'security_email_changed',
            context: [
                'new_email' => $newEmail,
                'ip'        => $request->getClientIp() ?? 'unknown',
                'device'    => $request->headers->get('User-Agent', 'unknown'),
                'datetime'  => (new \DateTimeImmutable())->format('d.m.Y H:i:s \U\T\C'),
            ],
        ));
    }

    /**
     * Уведомление об удалении аккаунта.
     * Email передаётся отдельно, так как сущность User уже удаляется к моменту отправки.
     */
    public function notifyAccountDeleted(string $email, Request $request): void
    {
        $this->dispatch(new EmailNotificationMessage(
            to: $email,
            subject: 'Аккаунт PassFort удалён',
            template: 'security_account_deleted',
            context: [
                'ip'       => $request->getClientIp() ?? 'unknown',
                'datetime' => (new \DateTimeImmutable())->format('d.m.Y H:i:s \U\T\C'),
            ],
        ));
    }

    private function dispatch(EmailNotificationMessage $message): void
    {
        try {
            $this->bus->dispatch($message);
        } catch (\Throwable $e) {
            // Уведомления не должны блокировать основной запрос
            $this->logger->warning('Failed to dispatch security notification email', [
                'to'    => $message->to,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
