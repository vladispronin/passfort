<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Service\Auth\TotpService;
use App\Tests\Functional\ApiTestCase;
use OTPHP\TOTP;

class AuthControllerTest extends ApiTestCase
{
    private string $validMasterPasswordHash;
    private string $validSalt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validMasterPasswordHash = str_repeat('a', 64);
        $this->validSalt = str_repeat('b', 32);
    }

    public function testKdfParamsReturnsDefaultForUnknownEmail(): void
    {
        $this->client->request('GET', '/api/v1/auth/kdf-params?email=unknown@example.com');

        $this->assertEquals(200, $this->getStatusCode());
        $response = $this->getJsonResponse();
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('salt', $response['data']);
        $this->assertArrayHasKey('algorithm', $response['data']);
    }

    public function testKdfParamsReturnsUserDataForExistingEmail(): void
    {
        $user = $this->createTestUser('kdf@example.com', $this->validMasterPasswordHash);

        $this->client->request('GET', '/api/v1/auth/kdf-params?email=kdf@example.com');

        $this->assertEquals(200, $this->getStatusCode());
        $response = $this->getJsonResponse();
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals($user->getSalt(), $response['data']['salt']);
    }

    public function testRegisterSuccess(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'masterPasswordHash' => $this->validMasterPasswordHash,
            'salt' => $this->validSalt,
            'kdfParams' => ['algorithm' => 'argon2id', 'iterations' => 3],
        ]);

        $this->assertEquals(201, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('newuser@example.com', $response['data']['email']);
        $this->assertArrayHasKey('id', $response['data']);
    }

    public function testRegisterReturnsConflictForDuplicateEmail(): void
    {
        $this->createTestUser('existing@example.com', $this->validMasterPasswordHash);

        $response = $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'existing@example.com',
            'masterPasswordHash' => $this->validMasterPasswordHash,
            'salt' => $this->validSalt,
            'kdfParams' => ['algorithm' => 'argon2id', 'iterations' => 3],
        ]);

        $this->assertEquals(409, $this->getStatusCode());
    }

    public function testRegisterValidationFails(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'not-an-email',
            'masterPasswordHash' => 'too_short',
            'salt' => 'short',
            'kdfParams' => [],
        ]);

        $this->assertContains($this->getStatusCode(), [400, 422]);
    }

    public function testLoginSuccess(): void
    {
        $this->createTestUser('login@example.com', $this->validMasterPasswordHash);

        $response = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'login@example.com',
            'masterPasswordHash' => $this->validMasterPasswordHash,
        ]);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('access_token', $response['data']);
        $this->assertArrayHasKey('refresh_token', $response['data']);
        $this->assertEquals('Bearer', $response['data']['token_type']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->createTestUser('loginwrong@example.com', $this->validMasterPasswordHash);

        $response = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'loginwrong@example.com',
            'masterPasswordHash' => str_repeat('z', 64),
        ]);

        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testLoginFailsForNonExistentUser(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'masterPasswordHash' => $this->validMasterPasswordHash,
        ]);

        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testLogoutRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/logout');
        // Без токена — 401
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testLogoutSuccess(): void
    {
        $user = $this->createTestUser('logout@example.com', $this->validMasterPasswordHash);
        $token = $this->getJwtToken($user);

        $this->jsonRequest('POST', '/api/v1/auth/logout', [], $token);

        $this->assertEquals(204, $this->getStatusCode());
    }

    public function testRefreshWithInvalidToken(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/refresh', [
            'refresh_token' => 'invalid_token_value',
        ]);

        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testLoginWithout2faStillWorksRegression(): void
    {
        // Регрессионный тест: логин без 2FA должен возвращать токены
        $this->createTestUser('regression@example.com', $this->validMasterPasswordHash);

        $response = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'regression@example.com',
            'masterPasswordHash' => $this->validMasterPasswordHash,
        ]);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('access_token', $response['data']);
        $this->assertArrayNotHasKey('requires_2fa', $response['data']);
    }

    public function testLoginReturns2faRequiredWhenEnabled(): void
    {
        $user = $this->createTestUser('2fa@example.com', $this->validMasterPasswordHash);
        $token = $this->getJwtToken($user);

        // Включаем 2FA
        $totpService = static::getContainer()->get(TotpService::class);
        $setupData = $totpService->generateSetupData($user);
        $totp = TOTP::createFromSecret($setupData['secret']);
        $totpService->verifyAndEnable($user, $totp->now());
        $this->em->flush();

        $response = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => '2fa@example.com',
            'masterPasswordHash' => $this->validMasterPasswordHash,
        ]);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertTrue($response['data']['requires_2fa']);
        $this->assertArrayHasKey('temp_token', $response['data']);
        $this->assertArrayNotHasKey('access_token', $response['data']);
    }

    public function testTwoFactorVerifyWithInvalidTempToken(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/2fa/verify', [
            'tempToken' => str_repeat('a', 64),
            'code' => '123456',
        ]);

        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testTwoFactorVerifyWithValidCode(): void
    {
        $user = $this->createTestUser('2faverify@example.com', $this->validMasterPasswordHash);

        // Включаем 2FA
        $totpService = static::getContainer()->get(TotpService::class);
        $setupData = $totpService->generateSetupData($user);
        $totp = TOTP::createFromSecret($setupData['secret']);
        $totpService->verifyAndEnable($user, $totp->now());
        $this->em->flush();

        // Логинимся — получаем temp_token
        $loginResponse = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => '2faverify@example.com',
            'masterPasswordHash' => $this->validMasterPasswordHash,
        ]);
        $tempToken = $loginResponse['data']['temp_token'];

        // Верифицируем 2FA код
        $response = $this->jsonRequest('POST', '/api/v1/auth/2fa/verify', [
            'tempToken' => $tempToken,
            'code' => $totp->now(),
        ]);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('access_token', $response['data']);
        $this->assertArrayHasKey('refresh_token', $response['data']);
        $this->assertEquals('Bearer', $response['data']['token_type']);
    }

    public function testTwoFactorVerifyWithInvalidCode(): void
    {
        $user = $this->createTestUser('2fafail@example.com', $this->validMasterPasswordHash);

        // Включаем 2FA
        $totpService = static::getContainer()->get(TotpService::class);
        $setupData = $totpService->generateSetupData($user);
        $totp = TOTP::createFromSecret($setupData['secret']);
        $totpService->verifyAndEnable($user, $totp->now());
        $this->em->flush();

        // Логинимся — получаем temp_token
        $loginResponse = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => '2fafail@example.com',
            'masterPasswordHash' => $this->validMasterPasswordHash,
        ]);
        $tempToken = $loginResponse['data']['temp_token'];

        // Неверный код
        $response = $this->jsonRequest('POST', '/api/v1/auth/2fa/verify', [
            'tempToken' => $tempToken,
            'code' => '000000',
        ]);

        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testTwoFactorVerifyWithBackupCode(): void
    {
        $user = $this->createTestUser('2fabackup@example.com', $this->validMasterPasswordHash);
        $token = $this->getJwtToken($user);

        // Включаем 2FA и получаем backup-коды
        $setupResponse = $this->jsonRequest('GET', '/api/v1/2fa/setup', [], $token);
        $secret = $setupResponse['data']['secret'];
        $totp = TOTP::createFromSecret($secret);
        $enableResponse = $this->jsonRequest('POST', '/api/v1/2fa/enable', ['code' => $totp->now()], $token);
        $backupCode = $enableResponse['data']['backup_codes'][0];

        // Логинимся — получаем temp_token
        $loginResponse = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => '2fabackup@example.com',
            'masterPasswordHash' => $this->validMasterPasswordHash,
        ]);
        $tempToken = $loginResponse['data']['temp_token'];

        // Верифицируем backup-кодом
        $response = $this->jsonRequest('POST', '/api/v1/auth/2fa/verify', [
            'tempToken' => $tempToken,
            'code' => $backupCode,
        ]);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('access_token', $response['data']);
    }

    public function testTwoFactorVerifyValidationError(): void
    {
        $response = $this->jsonRequest('POST', '/api/v1/auth/2fa/verify', [
            'tempToken' => 'short',
            'code' => '1',
        ]);

        $this->assertEquals(422, $this->getStatusCode());
    }
}
