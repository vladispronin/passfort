<?php

declare(strict_types=1);

namespace App\Message\Handler;

use App\Message\EmailNotificationMessage;
use App\Service\Email\EmailTemplateService;
use App\Service\Email\GmailApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EmailNotificationHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EmailTemplateService $emailTemplateService,
        private readonly GmailApiService $gmailApiService,
        #[Autowire(env: 'MAILER_FROM_EMAIL')]
        private readonly string $fromEmail,
        #[Autowire(env: 'MAILER_FROM_NAME')]
        private readonly string $fromName,
    ) {}

    public function __invoke(EmailNotificationMessage $message): void
    {
        try {
            $html = $this->emailTemplateService->renderHtml($message->template, $message->context);

            $this->gmailApiService->send(
                from: "{$this->fromName} <{$this->fromEmail}>",
                to: $message->to,
                subject: $message->subject,
                html: $html,
            );

            $this->logger->info('Email sent', [
                'to' => $message->to,
                'subject' => $message->subject,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Email send failed', [
                'to' => $message->to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
