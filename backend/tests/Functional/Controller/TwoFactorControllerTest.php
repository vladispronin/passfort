<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Service\Auth\TotpService;
use App\Tests\Functional\ApiTestCase;
use OTPHP\TOTP;

class TwoFactorControllerTest extends ApiTestCase
{
    private TotpService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = static::getContainer()->get(TotpService::class);
    }

    public function testSetupRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/v1/2fa/setup');
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testSetupReturnsSecretAndQrUri(): void
    {
        $user = $this->createTestUser();
        $token = $this->getJwtToken($user);

        $response = $this->jsonRequest('GET', '/api/v1/2fa/setup', [], $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('secret', $response['data']);
        $this->assertArrayHasKey('qr_uri', $response['data']);
        $this->assertStringStartsWith('otpauth://totp/', $response['data']['qr_uri']);
    }

    public function testSetupSavesPendingData(): void
    {
        $user = $this->createTestUser();
        $token = $this->getJwtToken($user);

        $this->jsonRequest('GET', '/api/v1/2fa/setup', [], $token);

        $this->em->clear();
        $freshUser = $this->em->find(User::class, $user->getId());
        $this->assertNotNull($freshUser->getTotpSetupData());
        $this->assertFalse($freshUser->is2faEnabled());
    }

    public function testEnableRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/v1/2fa/enable', ['code' => '123456']);
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testEnableWithValidCode(): void
    {
        $user = $this->createTestUser();
        $token = $this->getJwtToken($user);

        // Инициализируем настройку
        $setupResponse = $this->jsonRequest('GET', '/api/v1/2fa/setup', [], $token);
        $secret = $setupResponse['data']['secret'];

        // Генерируем валидный код
        $totp = TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        $response = $this->jsonRequest('POST', '/api/v1/2fa/enable', ['code' => $validCode], $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('backup_codes', $response['data']);
        $this->assertCount(10, $response['data']['backup_codes']);

        $this->em->clear();
        $freshUser = $this->em->find(User::class, $user->getId());
        $this->assertTrue($freshUser->is2faEnabled());
    }

    public function testEnableWithInvalidCode(): void
    {
        $user = $this->createTestUser();
        $token = $this->getJwtToken($user);

        $this->jsonRequest('GET', '/api/v1/2fa/setup', [], $token);

        $response = $this->jsonRequest('POST', '/api/v1/2fa/enable', ['code' => '000000'], $token);

        $this->assertEquals(400, $this->getStatusCode());
        $this->assertStringContainsString('Invalid TOTP code', $response['error']);
    }

    public function testEnableWithInvalidCodeFormat(): void
    {
        $user = $this->createTestUser();
        $token = $this->getJwtToken($user);

        $this->jsonRequest('GET', '/api/v1/2fa/setup', [], $token);

        $response = $this->jsonRequest('POST', '/api/v1/2fa/enable', ['code' => 'abc'], $token);

        $this->assertEquals(422, $this->getStatusCode());
    }

    public function testDisableRequiresAuth(): void
    {
        $this->client->request('DELETE', '/api/v1/2fa/disable', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['masterPasswordHash' => str_repeat('a', 64)]));

        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testDisableSuccess(): void
    {
        $masterPasswordHash = str_repeat('a', 64);
        $user = $this->createTestUser('test@example.com', $masterPasswordHash);
        $token = $this->getJwtToken($user);

        // Включаем 2FA
        $this->enableTwoFactor($user, $token);

        $this->em->clear();
        $freshUser = $this->em->find(User::class, $user->getId());
        $this->assertTrue($freshUser->is2faEnabled());

        // Отключаем 2FA
        $this->client->request('DELETE', '/api/v1/2fa/disable', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['masterPasswordHash' => $masterPasswordHash]));

        $this->assertEquals(204, $this->getStatusCode());

        $this->em->clear();
        $freshUser = $this->em->find(User::class, $user->getId());
        $this->assertFalse($freshUser->is2faEnabled());
        $this->assertNull($freshUser->getTotpSecret());
    }

    public function testDisableWithWrongPassword(): void
    {
        $user = $this->createTestUser();
        $token = $this->getJwtToken($user);

        $this->enableTwoFactor($user, $token);

        $this->client->request('DELETE', '/api/v1/2fa/disable', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode(['masterPasswordHash' => str_repeat('z', 64)]));

        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testDisableWithoutPassword(): void
    {
        $user = $this->createTestUser();
        $token = $this->getJwtToken($user);

        $this->client->request('DELETE', '/api/v1/2fa/disable', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ], json_encode([]));

        $this->assertEquals(422, $this->getStatusCode());
    }

    public function testStatusEndpointWhenDisabled(): void
    {
        $user = $this->createTestUser();
        $token = $this->getJwtToken($user);

        $response = $this->jsonRequest('GET', '/api/v1/2fa/status', [], $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertFalse($response['data']['is_enabled']);
        $this->assertFalse($response['data']['has_backup_codes']);
        $this->assertEquals(0, $response['data']['backup_codes_count']);
    }

    public function testStatusEndpointWhenEnabled(): void
    {
        $user = $this->createTestUser();
        $token = $this->getJwtToken($user);

        $this->enableTwoFactor($user, $token);

        $response = $this->jsonRequest('GET', '/api/v1/2fa/status', [], $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertTrue($response['data']['is_enabled']);
        $this->assertTrue($response['data']['has_backup_codes']);
        $this->assertEquals(10, $response['data']['backup_codes_count']);
    }

    public function testRegenerateBackupCodesSuccess(): void
    {
        $masterPasswordHash = str_repeat('a', 64);
        $user = $this->createTestUser('test@example.com', $masterPasswordHash);
        $token = $this->getJwtToken($user);

        $firstBackupCodes = $this->enableTwoFactor($user, $token);

        $response = $this->jsonRequest(
            'POST',
            '/api/v1/2fa/backup-codes/regenerate',
            ['masterPasswordHash' => $masterPasswordHash],
            $token,
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertCount(10, $response['data']['backup_codes']);
        $this->assertNotEquals($firstBackupCodes, $response['data']['backup_codes']);
    }

    public function testRegenerateBackupCodesWhen2faDisabled(): void
    {
        $masterPasswordHash = str_repeat('a', 64);
        $user = $this->createTestUser('test@example.com', $masterPasswordHash);
        $token = $this->getJwtToken($user);

        $response = $this->jsonRequest(
            'POST',
            '/api/v1/2fa/backup-codes/regenerate',
            ['masterPasswordHash' => $masterPasswordHash],
            $token,
        );

        $this->assertEquals(400, $this->getStatusCode());
    }

    /**
     * Включает 2FA для пользователя и возвращает backup-коды.
     */
    private function enableTwoFactor($user, string $token): array
    {
        $setupResponse = $this->jsonRequest('GET', '/api/v1/2fa/setup', [], $token);
        $secret = $setupResponse['data']['secret'];

        $totp = TOTP::createFromSecret($secret);
        $enableResponse = $this->jsonRequest('POST', '/api/v1/2fa/enable', ['code' => $totp->now()], $token);

        return $enableResponse['data']['backup_codes'] ?? [];
    }
}
