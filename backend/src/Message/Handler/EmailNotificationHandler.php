<?php

declare(strict_types=1);

namespace App\Message\Handler;

use App\Message\EmailNotificationMessage;
use App\Service\Email\EmailTemplateService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class EmailNotificationHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateService $emailTemplateService,
        #[Autowire(env: 'MAILER_FROM_EMAIL')]
        private readonly string $fromEmail,
        #[Autowire(env: 'MAILER_FROM_NAME')]
        private readonly string $fromName,
    ) {}

    public function __invoke(EmailNotificationMessage $message): void
    {
        try {
            $html = $this->emailTemplateService->renderHtml($message->template, $message->context);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($message->to)
                ->subject($message->subject)
                ->html($html);

            $this->mailer->send($email);

            $this->logger->info('Email sent', [
                'to' => $message->to,
                'subject' => $message->subject,
            ]);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Email send failed', [
                'to' => $message->to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
