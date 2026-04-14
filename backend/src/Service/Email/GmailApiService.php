<?php

declare(strict_types=1);

namespace App\Service\Email;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GmailApiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'GMAIL_CLIENT_ID')]
        private readonly string $clientId,
        #[Autowire(env: 'GMAIL_CLIENT_SECRET')]
        private readonly string $clientSecret,
        #[Autowire(env: 'GMAIL_REFRESH_TOKEN')]
        private readonly string $refreshToken,
    ) {}

    public function send(string $from, string $to, string $subject, string $html): void
    {
        $accessToken = $this->getAccessToken();
        $raw = $this->buildRawMessage($from, $to, $subject, $html);

        $this->httpClient->request('POST', 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
            'json' => ['raw' => $raw],
        ])->getStatusCode();
    }

    private function getAccessToken(): string
    {
        $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type'    => 'refresh_token',
            ],
        ]);

        return $response->toArray()['access_token'];
    }

    private function buildRawMessage(string $from, string $to, string $subject, string $html): string
    {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $message = implode("\r\n", [
            "From: {$from}",
            "To: {$to}",
            "Subject: {$encodedSubject}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "",
            base64_encode($html),
        ]);

        return rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
    }
}
