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

    public function testRenderSecurityNewLoginContainsIpAndDevice(): void
    {
        $html = $this->service->renderHtml('security_new_login', [
            'ip'       => '192.168.1.1',
            'device'   => 'Mozilla/5.0',
            'datetime' => '04.04.2026 12:00:00 UTC',
        ]);

        $this->assertStringContainsString('192.168.1.1', $html);
        $this->assertStringContainsString('Mozilla/5.0', $html);
        $this->assertStringContainsString('04.04.2026 12:00:00 UTC', $html);
        $this->assertStringContainsString('Новый вход в аккаунт', $html);
    }

    public function testRenderSecurityNewLoginEscapesXss(): void
    {
        $html = $this->service->renderHtml('security_new_login', [
            'ip'       => '<script>alert(1)</script>',
            'device'   => '<img src=x>',
            'datetime' => '',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function testRenderSecurityPasswordChangedContainsIp(): void
    {
        $html = $this->service->renderHtml('security_password_changed', [
            'ip'       => '10.0.0.1',
            'datetime' => '04.04.2026 15:00:00 UTC',
        ]);

        $this->assertStringContainsString('10.0.0.1', $html);
        $this->assertStringContainsString('04.04.2026 15:00:00 UTC', $html);
        $this->assertStringContainsString('Мастер-пароль изменён', $html);
    }

    public function testRenderSecurityPasswordChangedEscapesXss(): void
    {
        $html = $this->service->renderHtml('security_password_changed', [
            'ip'       => '<script>evil()</script>',
            'datetime' => '',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderSecurityAccountDeletedContainsIp(): void
    {
        $html = $this->service->renderHtml('security_account_deleted', [
            'ip'       => '172.16.0.1',
            'datetime' => '04.04.2026 18:00:00 UTC',
        ]);

        $this->assertStringContainsString('172.16.0.1', $html);
        $this->assertStringContainsString('04.04.2026 18:00:00 UTC', $html);
        $this->assertStringContainsString('Аккаунт удалён', $html);
    }

    public function testRenderSecurityNewLoginUsesDefaultsForMissingContext(): void
    {
        $html = $this->service->renderHtml('security_new_login', []);

        $this->assertStringContainsString('unknown', $html);
    }

    public function testRenderUnknownTemplateThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown email template: nonexistent');

        $this->service->renderHtml('nonexistent', []);
    }
}
