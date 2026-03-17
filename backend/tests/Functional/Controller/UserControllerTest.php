<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

class UserControllerTest extends ApiTestCase
{
    private string $masterPasswordHash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masterPasswordHash = str_repeat('a', 64);
    }

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
        // Создаём двух пользователей
        $this->createTestUser('taken@example.com', $this->masterPasswordHash);
        $user2 = $this->createTestUser('user2@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user2);

        $response = $this->jsonRequest('PUT', '/api/v1/user/me', [
            'email' => 'taken@example.com',
        ], $token);

        $this->assertEquals(409, $this->getStatusCode());
    }

    public function testDeleteUser(): void
    {
        $user = $this->createTestUser('delete@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $this->jsonRequest('DELETE', '/api/v1/user/me', [], $token);

        $this->assertEquals(204, $this->getStatusCode());
    }

    public function testUpdateRequiresAuth(): void
    {
        $this->jsonRequest('PUT', '/api/v1/user/me', ['email' => 'test@example.com']);
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testDeleteRequiresAuth(): void
    {
        $this->jsonRequest('DELETE', '/api/v1/user/me');
        $this->assertEquals(401, $this->getStatusCode());
    }
}
