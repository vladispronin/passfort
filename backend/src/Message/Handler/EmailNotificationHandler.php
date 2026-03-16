<?php

declare(strict_types=1);

namespace App\Message\Handler;

use App\Message\EmailNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EmailNotificationHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(EmailNotificationMessage $message): void
    {
        // TODO: Реализовать отправку email через Mailer
        $this->logger->info('Email notification', [
            'to' => $message->to,
            'subject' => $message->subject,
        ]);
    }
}
