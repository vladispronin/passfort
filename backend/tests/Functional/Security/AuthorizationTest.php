<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Tests\Functional\ApiTestCase;

/**
 * Тесты авторизации и защиты эндпоинтов.
 * Проверяет, что все защищённые маршруты требуют JWT-токен.
 */
class AuthorizationTest extends ApiTestCase
{
    private string $masterPasswordHash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masterPasswordHash = str_repeat('a', 64);
    }

    /**
     * Провайдер защищённых эндпоинтов — все должны возвращать 401 без токена.
     */
    public static function protectedEndpointsProvider(): array
    {
        return [
            'GET /user/me' => ['GET', '/api/v1/user/me'],
            'PUT /user/me' => ['PUT', '/api/v1/user/me'],
            'DELETE /user/me' => ['DELETE', '/api/v1/user/me'],
            'GET /vaults' => ['GET', '/api/v1/vaults'],
            'POST /vaults' => ['POST', '/api/v1/vaults'],
            'GET /vaults/{id}' => ['GET', '/api/v1/vaults/some-uuid'],
            'PUT /vaults/{id}' => ['PUT', '/api/v1/vaults/some-uuid'],
            'DELETE /vaults/{id}' => ['DELETE', '/api/v1/vaults/some-uuid'],
            'GET /vaults/{id}/items' => ['GET', '/api/v1/vaults/some-uuid/items'],
            'POST /vaults/{id}/items' => ['POST', '/api/v1/vaults/some-uuid/items'],
            'GET /vaults/{id}/items/{iid}' => ['GET', '/api/v1/vaults/some-uuid/items/some-item'],
            'PUT /vaults/{id}/items/{iid}' => ['PUT', '/api/v1/vaults/some-uuid/items/some-item'],
            'DELETE /vaults/{id}/items/{iid}' => ['DELETE', '/api/v1/vaults/some-uuid/items/some-item'],
            'PATCH /vaults/{id}/items/{iid}/favorite' => ['PATCH', '/api/v1/vaults/some-uuid/items/some-item/favorite'],
            'GET /vaults/{id}/categories' => ['GET', '/api/v1/vaults/some-uuid/categories'],
            'POST /vaults/{id}/categories' => ['POST', '/api/v1/vaults/some-uuid/categories'],
            'PUT /vaults/{id}/categories/{cid}' => ['PUT', '/api/v1/vaults/some-uuid/categories/some-cat'],
            'DELETE /vaults/{id}/categories/{cid}' => ['DELETE', '/api/v1/vaults/some-uuid/categories/some-cat'],
            'POST /auth/logout' => ['POST', '/api/v1/auth/logout'],
        ];
    }

    /**
     * @dataProvider protectedEndpointsProvider
     */
    public function testProtectedEndpointRequiresAuth(string $method, string $url): void
    {
        $this->jsonRequest($method, $url);

        $this->assertEquals(401, $this->getStatusCode(),
            "Endpoint {$method} {$url} должен возвращать 401 без авторизации"
        );
    }

    public function testPublicEndpointsAreAccessible(): void
    {
        // KDF params — публичный
        $this->client->request('GET', '/api/v1/auth/kdf-params?email=test@example.com');
        $this->assertNotEquals(401, $this->getStatusCode());

        // Register — публичный
        $this->client->request('POST', '/api/v1/auth/register',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'pub@example.com'])
        );
        $this->assertNotEquals(401, $this->getStatusCode());
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->jsonRequest('GET', '/api/v1/user/me', [], 'invalid.jwt.token');
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testExpiredTokenIsRejected(): void
    {
        // Просроченный JWT-токен (сгенерирован вручную с истёкшим exp)
        $expiredToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMDAwMX0.invalid_signature';

        $this->jsonRequest('GET', '/api/v1/user/me', [], $expiredToken);
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testUserCanOnlyAccessOwnData(): void
    {
        // Создаём двух пользователей
        $user1 = $this->createTestUser('sec1@example.com', $this->masterPasswordHash);
        $user2 = $this->createTestUser('sec2@example.com', $this->masterPasswordHash);

        $token1 = $this->getJwtToken($user1);
        $token2 = $this->getJwtToken($user2);

        // user1 создаёт vault
        $vaultResponse = $this->jsonRequest('POST', '/api/v1/vaults', ['name' => 'Private Vault'], $token1);
        $vaultId = $vaultResponse['data']['id'];

        // user2 не может получить доступ к vault user1
        $response = $this->jsonRequest('GET', "/api/v1/vaults/{$vaultId}", [], $token2);
        $this->assertContains($this->getStatusCode(), [403, 404]);
    }
}
