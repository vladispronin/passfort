<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

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
}
