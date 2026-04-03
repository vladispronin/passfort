<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Service\Auth\TotpService;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private TotpService $service;

    protected function setUp(): void
    {
        $this->service = new TotpService('test_app_secret_for_unit_tests');
    }

    private function makeUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        return $user;
    }

    public function testGenerateSetupDataReturnsTotpData(): void
    {
        $user = $this->makeUser();
        $result = $this->service->generateSetupData($user);

        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('qr_uri', $result);
        $this->assertStringStartsWith('otpauth://totp/', $result['qr_uri']);
        $this->assertNotEmpty($result['secret']);
    }

    public function testGenerateSetupDataSavesPendingData(): void
    {
        $user = $this->makeUser();
        $this->service->generateSetupData($user);

        $setupData = $user->getTotpSetupData();
        $this->assertNotNull($setupData);
        $this->assertArrayHasKey('encrypted_secret', $setupData);
        $this->assertArrayHasKey('created_at', $setupData);
    }

    public function testGenerateSetupDataDoesNotEnable2fa(): void
    {
        $user = $this->makeUser();
        $this->service->generateSetupData($user);

        $this->assertFalse($user->is2faEnabled());
        $this->assertNull($user->getTotpSecret());
    }

    public function testVerifyAndEnableWithValidCode(): void
    {
        $user = $this->makeUser();
        $setupData = $this->service->generateSetupData($user);

        // Генерируем актуальный TOTP код из секрета
        $totp = TOTP::createFromSecret($setupData['secret']);
        $validCode = $totp->now();

        $backupCodes = $this->service->verifyAndEnable($user, $validCode);

        $this->assertIsArray($backupCodes);
        $this->assertCount(10, $backupCodes);
        $this->assertTrue($user->is2faEnabled());
        $this->assertNotNull($user->getTotpSecret());
        $this->assertNull($user->getTotpSetupData());
        $this->assertCount(10, $user->getBackupCodes() ?? []);
    }

    public function testVerifyAndEnableWithInvalidCode(): void
    {
        $user = $this->makeUser();
        $this->service->generateSetupData($user);

        $result = $this->service->verifyAndEnable($user, '000000');

        $this->assertFalse($result);
        $this->assertFalse($user->is2faEnabled());
    }

    public function testVerifyAndEnableWithoutSetupData(): void
    {
        $user = $this->makeUser();
        // Не вызываем generateSetupData

        $result = $this->service->verifyAndEnable($user, '123456');

        $this->assertFalse($result);
    }

    public function testVerifyCodeWithValidCode(): void
    {
        $user = $this->makeUser();
        $setupData = $this->service->generateSetupData($user);

        $totp = TOTP::createFromSecret($setupData['secret']);
        $this->service->verifyAndEnable($user, $totp->now());

        // Теперь верифицируем через verifyCode
        $validCode = $totp->now();
        $this->assertTrue($this->service->verifyCode($user, $validCode));
    }

    public function testVerifyCodeWithInvalidCode(): void
    {
        $user = $this->makeUser();
        $setupData = $this->service->generateSetupData($user);

        $totp = TOTP::createFromSecret($setupData['secret']);
        $this->service->verifyAndEnable($user, $totp->now());

        $this->assertFalse($this->service->verifyCode($user, '000000'));
    }

    public function testVerifyCodeWhen2faDisabled(): void
    {
        $user = $this->makeUser();
        $this->assertFalse($this->service->verifyCode($user, '123456'));
    }

    public function testVerifyBackupCodeConsumesCode(): void
    {
        $user = $this->makeUser();
        $setupData = $this->service->generateSetupData($user);
        $totp = TOTP::createFromSecret($setupData['secret']);
        $backupCodes = $this->service->verifyAndEnable($user, $totp->now());

        $firstCode = $backupCodes[0];

        // Первое использование — успешно
        $this->assertTrue($this->service->verifyBackupCode($user, $firstCode));

        // Второе использование того же кода — должно быть отклонено
        $this->assertFalse($this->service->verifyBackupCode($user, $firstCode));
    }

    public function testVerifyBackupCodeDecreasesCount(): void
    {
        $user = $this->makeUser();
        $setupData = $this->service->generateSetupData($user);
        $totp = TOTP::createFromSecret($setupData['secret']);
        $backupCodes = $this->service->verifyAndEnable($user, $totp->now());

        $this->assertCount(10, $user->getBackupCodes());
        $this->service->verifyBackupCode($user, $backupCodes[0]);
        $this->assertCount(9, $user->getBackupCodes());
    }

    public function testVerifyBackupCodeWithInvalidCode(): void
    {
        $user = $this->makeUser();
        $setupData = $this->service->generateSetupData($user);
        $totp = TOTP::createFromSecret($setupData['secret']);
        $this->service->verifyAndEnable($user, $totp->now());

        $this->assertFalse($this->service->verifyBackupCode($user, 'INVALID1'));
    }

    public function testRegenerateBackupCodes(): void
    {
        $user = $this->makeUser();
        $setupData = $this->service->generateSetupData($user);
        $totp = TOTP::createFromSecret($setupData['secret']);
        $oldBackupCodes = $this->service->verifyAndEnable($user, $totp->now());
        $oldHashed = $user->getBackupCodes();

        $newRawCodes = $this->service->regenerateBackupCodes($user);

        $this->assertCount(10, $newRawCodes);
        $this->assertNotEquals($oldBackupCodes, $newRawCodes);
        $this->assertNotEquals($oldHashed, $user->getBackupCodes());
    }

    public function testDisableClearsTotpData(): void
    {
        $user = $this->makeUser();
        $setupData = $this->service->generateSetupData($user);
        $totp = TOTP::createFromSecret($setupData['secret']);
        $this->service->verifyAndEnable($user, $totp->now());

        $this->assertTrue($user->is2faEnabled());

        $this->service->disable($user);

        $this->assertFalse($user->is2faEnabled());
        $this->assertNull($user->getTotpSecret());
        $this->assertNull($user->getBackupCodes());
        $this->assertNull($user->getTotpSetupData());
    }
}
