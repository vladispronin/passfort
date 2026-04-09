<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

class VaultExportControllerTest extends ApiTestCase
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
            'name' => 'Export Test Vault',
        ], $token);

        $vaultId = $vaultResponse['data']['id'];

        return ['user' => $user, 'token' => $token, 'vaultId' => $vaultId];
    }

    private function validItemPayload(): array
    {
        return [
            'encryptedData' => base64_encode('encrypted_blob_content'),
            'iv' => base64_encode('1234567890123456'),
            'authTag' => base64_encode('1234567890123456'),
            'itemType' => 'login',
            'titleHint' => 'My Login',
        ];
    }

    public function testExportRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/v1/vaults/some-id/export');
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testExportForeignVaultFails(): void
    {
        $ctx1 = $this->setupUserWithVault('exportforeign1@example.com');
        $ctx2 = $this->setupUserWithVault('exportforeign2@example.com');

        $this->jsonRequest('GET', "/api/v1/vaults/{$ctx1['vaultId']}/export", [], $ctx2['token']);
        $this->assertEquals(404, $this->getStatusCode());
    }

    public function testExportEmptyVault(): void
    {
        $ctx = $this->setupUserWithVault('exportempty@example.com');

        $response = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/export", [], $ctx['token']);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('1.0', $response['data']['version']);
        $this->assertArrayHasKey('exportedAt', $response['data']);
        $this->assertArrayHasKey('vault', $response['data']);
        $this->assertEquals('Export Test Vault', $response['data']['vault']['name']);
        $this->assertIsArray($response['data']['categories']);
        $this->assertIsArray($response['data']['items']);
        $this->assertCount(0, $response['data']['categories']);
        $this->assertCount(0, $response['data']['items']);
    }

    public function testExportWithItemsAndCategories(): void
    {
        $ctx = $this->setupUserWithVault('exportwith@example.com');

        // Создаём категорию
        $catResponse = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/categories", [
            'name' => 'Work',
            'color' => '#aabbcc',
            'icon' => '💼',
        ], $ctx['token']);
        $categoryId = $catResponse['data']['id'];

        // Создаём item с категорией
        $payload = $this->validItemPayload();
        $payload['categoryId'] = $categoryId;
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $payload, $ctx['token']);

        // Создаём item без категории
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $this->validItemPayload(), $ctx['token']);

        $response = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/export", [], $ctx['token']);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertCount(1, $response['data']['categories']);
        $this->assertCount(2, $response['data']['items']);

        $exportedCategory = $response['data']['categories'][0];
        $this->assertEquals('Work', $exportedCategory['name']);
        $this->assertEquals('#aabbcc', $exportedCategory['color']);
        $this->assertEquals('💼', $exportedCategory['icon']);

        $itemWithCategory = null;
        foreach ($response['data']['items'] as $item) {
            if ($item['categoryId'] !== null) {
                $itemWithCategory = $item;
                break;
            }
        }
        $this->assertNotNull($itemWithCategory);
        $this->assertEquals($categoryId, $itemWithCategory['categoryId']);
        $this->assertArrayHasKey('encryptedData', $itemWithCategory);
        $this->assertArrayHasKey('iv', $itemWithCategory);
        $this->assertArrayHasKey('authTag', $itemWithCategory);
    }

    public function testImportRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/v1/vaults/some-id/import', ['items' => []]);
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testImportForeignVaultFails(): void
    {
        $ctx1 = $this->setupUserWithVault('importforeign1@example.com');
        $ctx2 = $this->setupUserWithVault('importforeign2@example.com');

        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx1['vaultId']}/import", ['items' => []], $ctx2['token']);
        $this->assertEquals(404, $this->getStatusCode());
    }

    public function testImportRequiresItemsField(): void
    {
        $ctx = $this->setupUserWithVault('importrequired@example.com');

        // Отправляем тело без поля items
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/import", ['categories' => []], $ctx['token']);
        $this->assertEquals(422, $this->getStatusCode());
    }

    public function testImportCreatesItems(): void
    {
        $ctx = $this->setupUserWithVault('importcreate@example.com');

        $payload = [
            'categories' => [],
            'items' => [
                $this->validItemPayload(),
                array_merge($this->validItemPayload(), ['titleHint' => 'Second Login', 'itemType' => 'note']),
            ],
        ];

        $response = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/import", $payload, $ctx['token']);

        $this->assertEquals(201, $this->getStatusCode());
        $this->assertEquals(0, $response['data']['imported']['categories']);
        $this->assertEquals(2, $response['data']['imported']['items']);

        // Проверяем, что items действительно созданы
        $listResponse = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/items", [], $ctx['token']);
        $this->assertEquals(2, $listResponse['meta']['total']);
    }

    public function testImportCreatesCategories(): void
    {
        $ctx = $this->setupUserWithVault('importcats@example.com');

        $payload = [
            'categories' => [
                ['id' => 'old-cat-id-1', 'name' => 'Social', 'color' => '#ff0000', 'icon' => '🔑'],
                ['id' => 'old-cat-id-2', 'name' => 'Work'],
            ],
            'items' => [],
        ];

        $response = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/import", $payload, $ctx['token']);

        $this->assertEquals(201, $this->getStatusCode());
        $this->assertEquals(2, $response['data']['imported']['categories']);
        $this->assertEquals(0, $response['data']['imported']['items']);

        $catsResponse = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/categories", [], $ctx['token']);
        $this->assertCount(2, $catsResponse['data']);
    }

    public function testImportWithCategoryIdRemapping(): void
    {
        $ctx = $this->setupUserWithVault('importremap@example.com');

        $oldCategoryId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $itemPayload = $this->validItemPayload();
        $itemPayload['categoryId'] = $oldCategoryId;

        $payload = [
            'categories' => [
                ['id' => $oldCategoryId, 'name' => 'Personal'],
            ],
            'items' => [$itemPayload],
        ];

        $response = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/import", $payload, $ctx['token']);

        $this->assertEquals(201, $this->getStatusCode());
        $this->assertEquals(1, $response['data']['imported']['categories']);
        $this->assertEquals(1, $response['data']['imported']['items']);

        // Получаем созданную категорию
        $catsResponse = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/categories", [], $ctx['token']);
        $newCategoryId = $catsResponse['data'][0]['id'];
        $this->assertNotEquals($oldCategoryId, $newCategoryId);

        // Проверяем, что item привязан к новой категории
        $itemsResponse = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/items", [], $ctx['token']);
        $this->assertEquals($newCategoryId, $itemsResponse['data'][0]['categoryId']);
    }

    public function testImportIgnoresInvalidItems(): void
    {
        $ctx = $this->setupUserWithVault('importinvalid@example.com');

        $payload = [
            'items' => [
                $this->validItemPayload(),                        // корректный
                ['encryptedData' => '', 'iv' => 'x', 'authTag' => 'x', 'itemType' => 'login', 'titleHint' => 'Bad'],  // пустой encryptedData
                ['encryptedData' => 'abc', 'iv' => 'short', 'authTag' => base64_encode('1234567890123456'), 'itemType' => 'login', 'titleHint' => 'Bad'], // iv слишком короткий
                ['encryptedData' => 'abc', 'iv' => base64_encode('1234567890123456'), 'authTag' => base64_encode('1234567890123456'), 'itemType' => 'bad_type', 'titleHint' => 'Bad'], // неверный itemType
                [],                                               // пустой
            ],
        ];

        $response = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/import", $payload, $ctx['token']);

        $this->assertEquals(201, $this->getStatusCode());
        $this->assertEquals(1, $response['data']['imported']['items']);
    }
}
