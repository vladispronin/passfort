<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

class CategoryControllerTest extends ApiTestCase
{
    private string $masterPasswordHash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masterPasswordHash = str_repeat('a', 64);
    }

    private function setupUserWithVault(string $email): array
    {
        $user = $this->createTestUser($email, $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $vaultResponse = $this->jsonRequest('POST', '/api/v1/vaults', [
            'name' => 'Category Test Vault',
        ], $token);

        $vaultId = $vaultResponse['data']['id'];

        return ['user' => $user, 'token' => $token, 'vaultId' => $vaultId];
    }

    private function validCategoryPayload(): array
    {
        return [
            'name' => 'Work',
            'color' => '#ff5733',
            'icon' => 'briefcase',
        ];
    }

    public function testListCategoriesRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/v1/vaults/some-id/categories');
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testListCategoriesEmpty(): void
    {
        $ctx = $this->setupUserWithVault('catlist@example.com');

        $response = $this->jsonRequest(
            'GET',
            "/api/v1/vaults/{$ctx['vaultId']}/categories",
            [],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertIsArray($response['data']);
        $this->assertCount(0, $response['data']);
    }

    public function testCreateCategory(): void
    {
        $ctx = $this->setupUserWithVault('catcreate@example.com');

        $response = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/categories",
            $this->validCategoryPayload(),
            $ctx['token']
        );

        $this->assertEquals(201, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('Work', $response['data']['name']);
        $this->assertEquals('#ff5733', $response['data']['color']);
        $this->assertEquals('briefcase', $response['data']['icon']);
        $this->assertArrayHasKey('id', $response['data']);
    }

    public function testCreateCategoryRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/v1/vaults/some-id/categories', $this->validCategoryPayload());
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testCreateCategoryValidationFails(): void
    {
        $ctx = $this->setupUserWithVault('catvalidate@example.com');

        $response = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/categories",
            [
                'name' => '',            // обязательное поле пустое
                'color' => 'not-a-color', // неверный формат цвета
            ],
            $ctx['token']
        );

        $this->assertContains($this->getStatusCode(), [400, 422]);
    }

    public function testUpdateCategory(): void
    {
        $ctx = $this->setupUserWithVault('catupdate@example.com');

        $createResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/categories",
            $this->validCategoryPayload(),
            $ctx['token']
        );
        $categoryId = $createResponse['data']['id'];

        $response = $this->jsonRequest(
            'PUT',
            "/api/v1/vaults/{$ctx['vaultId']}/categories/{$categoryId}",
            ['name' => 'Personal', 'color' => '#00ff00', 'icon' => 'home'],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals('Personal', $response['data']['name']);
        $this->assertEquals('#00ff00', $response['data']['color']);
    }

    public function testDeleteCategory(): void
    {
        $ctx = $this->setupUserWithVault('catdelete@example.com');

        $createResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/categories",
            $this->validCategoryPayload(),
            $ctx['token']
        );
        $categoryId = $createResponse['data']['id'];

        $this->jsonRequest(
            'DELETE',
            "/api/v1/vaults/{$ctx['vaultId']}/categories/{$categoryId}",
            [],
            $ctx['token']
        );

        $this->assertEquals(204, $this->getStatusCode());
    }

    public function testUpdateCategoryNotFound(): void
    {
        $ctx = $this->setupUserWithVault('catnotfound@example.com');

        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this->jsonRequest(
            'PUT',
            "/api/v1/vaults/{$ctx['vaultId']}/categories/{$fakeId}",
            $this->validCategoryPayload(),
            $ctx['token']
        );

        $this->assertEquals(404, $this->getStatusCode());
    }

    public function testCannotAccessCategoryFromOtherVault(): void
    {
        // user1 создаёт vault и категорию
        $ctx1 = $this->setupUserWithVault('cataccess1@example.com');
        $createResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx1['vaultId']}/categories",
            $this->validCategoryPayload(),
            $ctx1['token']
        );
        $categoryId = $createResponse['data']['id'];

        // user2 создаёт свой vault
        $ctx2 = $this->setupUserWithVault('cataccess2@example.com');

        // user2 пытается удалить категорию из vault user1 через свой vault
        $response = $this->jsonRequest(
            'DELETE',
            "/api/v1/vaults/{$ctx2['vaultId']}/categories/{$categoryId}",
            [],
            $ctx2['token']
        );

        $this->assertContains($this->getStatusCode(), [403, 404]);
    }
}
