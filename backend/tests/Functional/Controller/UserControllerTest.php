<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\RefreshToken;
use App\Service\Auth\RefreshTokenService;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Request;

class UserControllerTest extends ApiTestCase
{
    private string $masterPasswordHash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masterPasswordHash = str_repeat('a', 64);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Создаёт пользователя, vault и vault item через API. Возвращает токен и ID записи.
     */
    private function setupUserWithVaultItem(string $email): array
    {
        $user = $this->createTestUser($email, $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $vaultResponse = $this->jsonRequest('POST', '/api/v1/vaults', [
            'name' => 'Test Vault',
        ], $token);
        $vaultId = $vaultResponse['data']['id'];

        $iv = base64_encode('123456789012');       // 12 байт → 16 base64 символов
        $authTag = base64_encode('1234567890123456'); // 16 байт → 24 base64 символа

        $itemResponse = $this->jsonRequest('POST', "/api/v1/vaults/{$vaultId}/items", [
            'encryptedData' => base64_encode('encrypted_content'),
            'iv' => $iv,
            'authTag' => $authTag,
            'itemType' => 'login',
            'titleHint' => 'Test Item',
        ], $token);
        $itemId = $itemResponse['data']['id'];

        return [
            'user' => $user,
            'token' => $token,
            'vaultId' => $vaultId,
            'itemId' => $itemId,
        ];
    }

    private function validChangeMasterPasswordPayload(array $items = []): array
    {
        return [
            'currentMasterPasswordHash' => $this->masterPasswordHash,
            'newMasterPasswordHash' => str_repeat('b', 64),
            'newSalt' => str_repeat('c', 44),
            'newKdfParams' => [
                'algorithm' => 'PBKDF2',
                'iterations' => 600000,
                'hash' => 'SHA-256',
                'keyLength' => 256,
            ],
            'items' => $items,
        ];
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/user/me
    // -------------------------------------------------------------------------

    public function testMeRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/v1/user/me');
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testMeReturnsUserInfo(): void
    {
        $user = $this->createTestUser('me@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $response = $this->jsonRequest('GET', '/api/v1/user/me', [], $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('me@example.com', $response['data']['email']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertArrayHasKey('kdfParams', $response['data']);
        $this->assertArrayHasKey('salt', $response['data']);
        $this->assertArrayHasKey('createdAt', $response['data']);
    }

    // -------------------------------------------------------------------------
    // PUT /api/v1/user/me
    // -------------------------------------------------------------------------

    public function testUpdateEmailSuccess(): void
    {
        $user = $this->createTestUser('update@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $response = $this->jsonRequest('PUT', '/api/v1/user/me', [
            'email' => 'updated@example.com',
        ], $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals('updated@example.com', $response['data']['email']);
    }

    public function testUpdateEmailConflict(): void
    {
        $this->createTestUser('taken@example.com', $this->masterPasswordHash);
        $user2 = $this->createTestUser('user2@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user2);

        $this->jsonRequest('PUT', '/api/v1/user/me', [
            'email' => 'taken@example.com',
        ], $token);

        $this->assertEquals(409, $this->getStatusCode());
    }

    public function testUpdateRequiresAuth(): void
    {
        $this->jsonRequest('PUT', '/api/v1/user/me', ['email' => 'test@example.com']);
        $this->assertEquals(401, $this->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/user/me
    // -------------------------------------------------------------------------

    public function testDeleteUser(): void
    {
        $user = $this->createTestUser('delete@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $this->jsonRequest('DELETE', '/api/v1/user/me', [], $token);

        $this->assertEquals(204, $this->getStatusCode());
    }

    public function testDeleteRequiresAuth(): void
    {
        $this->jsonRequest('DELETE', '/api/v1/user/me');
        $this->assertEquals(401, $this->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/user/master-password
    // -------------------------------------------------------------------------

    public function testChangeMasterPasswordRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/v1/user/master-password', $this->validChangeMasterPasswordPayload());
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testChangeMasterPasswordSuccess(): void
    {
        $setup = $this->setupUserWithVaultItem('mp_success@example.com');
        $token = $setup['token'];
        $itemId = $setup['itemId'];

        $iv = base64_encode('123456789012');
        $authTag = base64_encode('1234567890123456');

        $payload = $this->validChangeMasterPasswordPayload([
            [
                'id' => $itemId,
                'encryptedData' => base64_encode('new_encrypted_content'),
                'iv' => $iv,
                'authTag' => $authTag,
            ],
        ]);

        $response = $this->jsonRequest('POST', '/api/v1/user/master-password', $payload, $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('access_token', $response['data']);
        $this->assertArrayHasKey('refresh_token', $response['data']);
    }

    public function testChangeMasterPasswordSuccessWithNoItems(): void
    {
        $user = $this->createTestUser('mp_empty@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $payload = $this->validChangeMasterPasswordPayload([]);
        $response = $this->jsonRequest('POST', '/api/v1/user/master-password', $payload, $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('access_token', $response['data']);
    }

    public function testChangeMasterPasswordInvalidCurrentPassword(): void
    {
        $user = $this->createTestUser('mp_invalid@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $payload = $this->validChangeMasterPasswordPayload();
        $payload['currentMasterPasswordHash'] = str_repeat('z', 64); // неверный хэш

        $this->jsonRequest('POST', '/api/v1/user/master-password', $payload, $token);

        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testChangeMasterPasswordValidationError(): void
    {
        $user = $this->createTestUser('mp_validation@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        // Слишком короткий currentMasterPasswordHash (< 64 символов)
        $this->jsonRequest('POST', '/api/v1/user/master-password', [
            'currentMasterPasswordHash' => 'tooshort',
            'newMasterPasswordHash' => str_repeat('b', 64),
            'newSalt' => str_repeat('c', 44),
            'newKdfParams' => ['algorithm' => 'PBKDF2'],
            'items' => [],
        ], $token);

        $this->assertEquals(422, $this->getStatusCode());
    }

    public function testChangeMasterPasswordForeignItemsRejected(): void
    {
        $user = $this->createTestUser('mp_foreign@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        // Создаём второго пользователя с записью
        $setup2 = $this->setupUserWithVaultItem('mp_foreign2@example.com');
        $foreignItemId = $setup2['itemId'];

        $iv = base64_encode('123456789012');
        $authTag = base64_encode('1234567890123456');

        $payload = $this->validChangeMasterPasswordPayload([
            [
                'id' => $foreignItemId,
                'encryptedData' => base64_encode('enc'),
                'iv' => $iv,
                'authTag' => $authTag,
            ],
        ]);

        $this->jsonRequest('POST', '/api/v1/user/master-password', $payload, $token);

        $this->assertEquals(403, $this->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/user/sessions
    // -------------------------------------------------------------------------

    public function testGetSessionsRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/v1/user/sessions');
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testGetSessionsReturnsActiveSessions(): void
    {
        $user = $this->createTestUser('sessions_list@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        // Создаём refresh-токен через сервис (имитация логина)
        $refreshTokenService = static::getContainer()->get(RefreshTokenService::class);
        $refreshTokenService->createRefreshToken($user, Request::create('/'));

        $response = $this->jsonRequest('GET', '/api/v1/user/sessions', [], $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
        $this->assertCount(1, $response['data']);
        $session = $response['data'][0];
        $this->assertArrayHasKey('id', $session);
        $this->assertArrayHasKey('ipAddress', $session);
        $this->assertArrayHasKey('deviceInfo', $session);
        $this->assertArrayHasKey('createdAt', $session);
        $this->assertArrayHasKey('expiresAt', $session);
        $this->assertArrayHasKey('isCurrent', $session);
    }

    public function testGetSessionsExcludesExpiredTokens(): void
    {
        $user = $this->createTestUser('sessions_expired@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        // Создаём истёкший refresh-токен напрямую через EM
        $expiredToken = new RefreshToken();
        $expiredToken->setUser($user);
        $expiredToken->setTokenHash(hash('sha256', 'expired_token_value'));
        $expiredToken->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->em->persist($expiredToken);
        $this->em->flush();

        $response = $this->jsonRequest('GET', '/api/v1/user/sessions', [], $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertCount(0, $response['data']);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/user/sessions/{id}
    // -------------------------------------------------------------------------

    public function testDeleteSessionRequiresAuth(): void
    {
        $this->jsonRequest('DELETE', '/api/v1/user/sessions/some-id');
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testDeleteSessionSuccess(): void
    {
        $user = $this->createTestUser('sessions_delete@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $refreshTokenService = static::getContainer()->get(RefreshTokenService::class);
        $refreshTokenService->createRefreshToken($user, Request::create('/'));

        // Получаем ID созданного токена из БД
        $refreshToken = $this->em->getRepository(RefreshToken::class)->findOneBy(['user' => $user]);
        $sessionId = $refreshToken->getId()->toRfc4122();

        $this->jsonRequest('DELETE', "/api/v1/user/sessions/{$sessionId}", [], $token);

        $this->assertEquals(204, $this->getStatusCode());

        // Токен должен быть удалён из БД
        $this->em->clear();
        $count = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM refresh_tokens');
        $this->assertEquals(0, $count);
    }

    public function testDeleteSessionNotFound(): void
    {
        $user = $this->createTestUser('sessions_notfound@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $this->jsonRequest('DELETE', '/api/v1/user/sessions/00000000-0000-0000-0000-000000000000', [], $token);

        $this->assertEquals(404, $this->getStatusCode());
    }

    public function testDeleteSessionBelongsToOtherUser(): void
    {
        $user1 = $this->createTestUser('sessions_owner@example.com', $this->masterPasswordHash);
        $user2 = $this->createTestUser('sessions_attacker@example.com', $this->masterPasswordHash);
        $token2 = $this->getJwtToken($user2);

        // Создаём сессию для user1
        $refreshTokenService = static::getContainer()->get(RefreshTokenService::class);
        $refreshTokenService->createRefreshToken($user1, Request::create('/'));

        $foreignToken = $this->em->getRepository(RefreshToken::class)->findOneBy(['user' => $user1]);
        $foreignSessionId = $foreignToken->getId()->toRfc4122();

        // user2 пытается удалить сессию user1 — должен получить 404
        $this->jsonRequest('DELETE', "/api/v1/user/sessions/{$foreignSessionId}", [], $token2);

        $this->assertEquals(404, $this->getStatusCode());
    }

    public function testOldTokensInvalidatedAfterPasswordChange(): void
    {
        // Логинимся, чтобы получить реальный refresh token
        $this->createTestUser('mp_revoke@example.com', $this->masterPasswordHash);

        $loginResponse = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'mp_revoke@example.com',
            'masterPasswordHash' => $this->masterPasswordHash,
        ]);
        $this->assertEquals(200, $this->getStatusCode());
        $oldRefreshToken = $loginResponse['data']['refresh_token'];
        $accessToken = $loginResponse['data']['access_token'];

        // Меняем мастер-пароль
        $payload = $this->validChangeMasterPasswordPayload([]);
        $this->jsonRequest('POST', '/api/v1/user/master-password', $payload, $accessToken);
        $this->assertEquals(200, $this->getStatusCode());

        // Пытаемся использовать старый refresh token — должен вернуть 401
        $this->jsonRequest('POST', '/api/v1/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);
        $this->assertEquals(401, $this->getStatusCode());
    }
}
