<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Email;

use App\Service\Email\EmailTemplateService;
use PHPUnit\Framework\TestCase;

class EmailTemplateServiceTest extends TestCase
{
    private EmailTemplateService $service;

    protected function setUp(): void
    {
        $this->service = new EmailTemplateService();
    }

    public function testRenderEmailVerificationContainsLink(): void
    {
        $link = 'http://localhost:19080/api/v1/auth/verify-email?token=abc123';

        $html = $this->service->renderHtml('email_verification', [
            'verification_link' => $link,
            'expires_hours' => 24,
        ]);

        $this->assertStringContainsString($link, $html);
        $this->assertStringContainsString('Подтвердите ваш email', $html);
        $this->assertStringContainsString('24', $html);
    }

    public function testRenderEmailVerificationEscapesLink(): void
    {
        $link = 'http://localhost/?a=1&b=<script>';

        $html = $this->service->renderHtml('email_verification', [
            'verification_link' => $link,
            'expires_hours' => 24,
        ]);

        // htmlspecialchars должен экранировать < и >
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderUnknownTemplateThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown email template: nonexistent');

        $this->service->renderHtml('nonexistent', []);
    }
}
