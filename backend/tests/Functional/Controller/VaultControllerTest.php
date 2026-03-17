<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Vault;
use App\Tests\Functional\ApiTestCase;

class VaultControllerTest extends ApiTestCase
{
    private string $masterPasswordHash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masterPasswordHash = str_repeat('a', 64);
    }

    private function createVaultForUser(string $email): array
    {
        $user = $this->createTestUser($email, $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        // Создаём vault через API
        $response = $this->jsonRequest('POST', '/api/v1/vaults', [
            'name' => 'Test Vault',
        ], $token);

        return ['user' => $user, 'token' => $token, 'vault' => $response['data'] ?? null];
    }

    public function testListRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/v1/vaults');
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testListReturnsVaults(): void
    {
        $user = $this->createTestUser('listvault@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $response = $this->jsonRequest('GET', '/api/v1/vaults', [], $token);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
    }

    public function testCreateVault(): void
    {
        $user = $this->createTestUser('createvault@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $response = $this->jsonRequest('POST', '/api/v1/vaults', [
            'name' => 'My New Vault',
        ], $token);

        $this->assertEquals(201, $this->getStatusCode());
        $this->assertEquals('My New Vault', $response['data']['name']);
        $this->assertArrayHasKey('id', $response['data']);
    }

    public function testCreateVaultRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/v1/vaults', ['name' => 'Vault']);
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testShowVault(): void
    {
        $data = $this->createVaultForUser('showvault@example.com');
        $vaultId = $data['vault']['id'];

        $response = $this->jsonRequest('GET', "/api/v1/vaults/{$vaultId}", [], $data['token']);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals($vaultId, $response['data']['id']);
        $this->assertEquals('Test Vault', $response['data']['name']);
    }

    public function testShowVaultNotFound(): void
    {
        $user = $this->createTestUser('notvault@example.com', $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this->jsonRequest('GET', "/api/v1/vaults/{$fakeId}", [], $token);

        $this->assertEquals(404, $this->getStatusCode());
    }

    public function testUpdateVault(): void
    {
        $data = $this->createVaultForUser('updatevault@example.com');
        $vaultId = $data['vault']['id'];

        $response = $this->jsonRequest('PUT', "/api/v1/vaults/{$vaultId}", [
            'name' => 'Updated Vault Name',
        ], $data['token']);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals('Updated Vault Name', $response['data']['name']);
    }

    public function testDeleteVault(): void
    {
        $data = $this->createVaultForUser('deletevault@example.com');
        $vaultId = $data['vault']['id'];

        $this->jsonRequest('DELETE', "/api/v1/vaults/{$vaultId}", [], $data['token']);

        $this->assertEquals(204, $this->getStatusCode());
    }

    public function testCannotAccessOtherUsersVault(): void
    {
        // Создаём vault для user1
        $data1 = $this->createVaultForUser('vaultuser1@example.com');
        $vaultId = $data1['vault']['id'];

        // user2 пытается получить доступ к vault user1
        $user2 = $this->createTestUser('vaultuser2@example.com', $this->masterPasswordHash);
        $token2 = $this->getJwtToken($user2);

        $response = $this->jsonRequest('GET', "/api/v1/vaults/{$vaultId}", [], $token2);

        // Должен вернуть 404 (не 403, чтобы не раскрывать существование ресурса)
        $this->assertContains($this->getStatusCode(), [403, 404]);
    }
}
