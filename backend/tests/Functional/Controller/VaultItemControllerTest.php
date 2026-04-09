<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

class VaultItemControllerTest extends ApiTestCase
{
    private string $masterPasswordHash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masterPasswordHash = str_repeat('a', 64);
    }

    /**
     * Создаёт пользователя и хранилище, возвращает массив с данными.
     */
    private function setupUserWithVault(string $email): array
    {
        $user = $this->createTestUser($email, $this->masterPasswordHash);
        $token = $this->getJwtToken($user);

        $vaultResponse = $this->jsonRequest('POST', '/api/v1/vaults', [
            'name' => 'Item Test Vault',
        ], $token);

        $vaultId = $vaultResponse['data']['id'];

        return ['user' => $user, 'token' => $token, 'vaultId' => $vaultId];
    }

    private function validItemPayload(): array
    {
        return [
            'encryptedData' => base64_encode('encrypted_blob_content'),
            'iv' => base64_encode('1234567890123456'), // 16 байт → 24 символа base64
            'authTag' => base64_encode('1234567890123456'), // 16 байт → 24 символа base64
            'itemType' => 'login',
            'titleHint' => 'My Login',
        ];
    }

    public function testListItemsRequiresAuth(): void
    {
        $this->jsonRequest('GET', '/api/v1/vaults/some-id/items');
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testCreateItem(): void
    {
        $ctx = $this->setupUserWithVault('itemcreate@example.com');

        $response = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            $this->validItemPayload(),
            $ctx['token']
        );

        $this->assertEquals(201, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertEquals('My Login', $response['data']['titleHint']);
        $this->assertEquals('login', $response['data']['itemType']);
        $this->assertFalse($response['data']['isFavorite']);
    }

    public function testListItems(): void
    {
        $ctx = $this->setupUserWithVault('itemlist@example.com');

        // Создаём элемент
        $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            $this->validItemPayload(),
            $ctx['token']
        );

        $response = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/items", [], $ctx['token']);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertIsArray($response['data']);
        $this->assertCount(1, $response['data']);
    }

    public function testShowItem(): void
    {
        $ctx = $this->setupUserWithVault('itemshow@example.com');

        $createResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            $this->validItemPayload(),
            $ctx['token']
        );
        $itemId = $createResponse['data']['id'];

        $response = $this->jsonRequest(
            'GET',
            "/api/v1/vaults/{$ctx['vaultId']}/items/{$itemId}",
            [],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals($itemId, $response['data']['id']);
    }

    public function testUpdateItem(): void
    {
        $ctx = $this->setupUserWithVault('itemupdate@example.com');

        $createResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            $this->validItemPayload(),
            $ctx['token']
        );
        $itemId = $createResponse['data']['id'];

        $updatedPayload = $this->validItemPayload();
        $updatedPayload['titleHint'] = 'Updated Title';
        $updatedPayload['itemType'] = 'note';

        $response = $this->jsonRequest(
            'PUT',
            "/api/v1/vaults/{$ctx['vaultId']}/items/{$itemId}",
            $updatedPayload,
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals('Updated Title', $response['data']['titleHint']);
        $this->assertEquals('note', $response['data']['itemType']);
    }

    public function testDeleteItem(): void
    {
        $ctx = $this->setupUserWithVault('itemdelete@example.com');

        $createResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            $this->validItemPayload(),
            $ctx['token']
        );
        $itemId = $createResponse['data']['id'];

        $this->jsonRequest(
            'DELETE',
            "/api/v1/vaults/{$ctx['vaultId']}/items/{$itemId}",
            [],
            $ctx['token']
        );

        $this->assertEquals(204, $this->getStatusCode());
    }

    public function testToggleFavorite(): void
    {
        $ctx = $this->setupUserWithVault('itemfav@example.com');

        $createResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            $this->validItemPayload(),
            $ctx['token']
        );
        $itemId = $createResponse['data']['id'];
        $this->assertFalse($createResponse['data']['isFavorite']);

        // Переключаем в избранное
        $response = $this->jsonRequest(
            'PATCH',
            "/api/v1/vaults/{$ctx['vaultId']}/items/{$itemId}/favorite",
            [],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertTrue($response['data']['isFavorite']);

        // Переключаем обратно
        $response2 = $this->jsonRequest(
            'PATCH',
            "/api/v1/vaults/{$ctx['vaultId']}/items/{$itemId}/favorite",
            [],
            $ctx['token']
        );
        $this->assertFalse($response2['data']['isFavorite']);
    }

    public function testShowItemNotFound(): void
    {
        $ctx = $this->setupUserWithVault('itemnotfound@example.com');

        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this->jsonRequest(
            'GET',
            "/api/v1/vaults/{$ctx['vaultId']}/items/{$fakeId}",
            [],
            $ctx['token']
        );

        $this->assertEquals(404, $this->getStatusCode());
    }

    public function testCreateItemValidationFails(): void
    {
        $ctx = $this->setupUserWithVault('itemvalidate@example.com');

        $response = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            [
                'encryptedData' => '',    // обязательное поле пустое
                'iv' => 'short',          // слишком короткое
                'authTag' => 'short',     // слишком короткое
                'itemType' => 'invalid',  // недопустимый тип
                'titleHint' => '',        // обязательное поле пустое
            ],
            $ctx['token']
        );

        $this->assertContains($this->getStatusCode(), [400, 422]);
    }

    public function testListResponseHasMeta(): void
    {
        $ctx = $this->setupUserWithVault('itemmeta@example.com');

        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $this->validItemPayload(), $ctx['token']);

        $response = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/items", [], $ctx['token']);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('meta', $response);
        $this->assertArrayHasKey('total', $response['meta']);
        $this->assertArrayHasKey('page', $response['meta']);
        $this->assertArrayHasKey('limit', $response['meta']);
        $this->assertArrayHasKey('pages', $response['meta']);
        $this->assertEquals(1, $response['meta']['total']);
        $this->assertEquals(1, $response['meta']['page']);
    }

    public function testListWithTypeFilter(): void
    {
        $ctx = $this->setupUserWithVault('itemtypefilter@example.com');

        $loginPayload = $this->validItemPayload();
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $loginPayload, $ctx['token']);

        $notePayload = $this->validItemPayload();
        $notePayload['itemType'] = 'note';
        $notePayload['titleHint'] = 'My Note';
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $notePayload, $ctx['token']);

        $response = $this->jsonRequest(
            'GET',
            "/api/v1/vaults/{$ctx['vaultId']}/items?type=login",
            [],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertCount(1, $response['data']);
        $this->assertEquals('login', $response['data'][0]['itemType']);
        $this->assertEquals(1, $response['meta']['total']);
    }

    public function testListWithSearchFilter(): void
    {
        $ctx = $this->setupUserWithVault('itemsearch@example.com');

        $githubPayload = $this->validItemPayload();
        $githubPayload['titleHint'] = 'GitHub Account';
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $githubPayload, $ctx['token']);

        $gmailPayload = $this->validItemPayload();
        $gmailPayload['titleHint'] = 'Gmail';
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $gmailPayload, $ctx['token']);

        $response = $this->jsonRequest(
            'GET',
            "/api/v1/vaults/{$ctx['vaultId']}/items?q=github",
            [],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertCount(1, $response['data']);
        $this->assertStringContainsStringIgnoringCase('github', $response['data'][0]['titleHint']);
        $this->assertEquals(1, $response['meta']['total']);
    }

    public function testListWithFavoriteFilter(): void
    {
        $ctx = $this->setupUserWithVault('itemfavfilter@example.com');

        $createResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            $this->validItemPayload(),
            $ctx['token']
        );
        $itemId = $createResponse['data']['id'];

        $payload2 = $this->validItemPayload();
        $payload2['titleHint'] = 'Not Favorite';
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $payload2, $ctx['token']);

        $this->jsonRequest(
            'PATCH',
            "/api/v1/vaults/{$ctx['vaultId']}/items/{$itemId}/favorite",
            [],
            $ctx['token']
        );

        $response = $this->jsonRequest(
            'GET',
            "/api/v1/vaults/{$ctx['vaultId']}/items?favorite=true",
            [],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertCount(1, $response['data']);
        $this->assertTrue($response['data'][0]['isFavorite']);
        $this->assertEquals(1, $response['meta']['total']);
    }

    public function testListPagination(): void
    {
        $ctx = $this->setupUserWithVault('itempagination@example.com');

        for ($i = 1; $i <= 5; $i++) {
            $payload = $this->validItemPayload();
            $payload['titleHint'] = "Item {$i}";
            $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $payload, $ctx['token']);
        }

        $response = $this->jsonRequest(
            'GET',
            "/api/v1/vaults/{$ctx['vaultId']}/items?page=1&limit=2",
            [],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertCount(2, $response['data']);
        $this->assertEquals(5, $response['meta']['total']);
        $this->assertEquals(1, $response['meta']['page']);
        $this->assertEquals(2, $response['meta']['limit']);
        $this->assertEquals(3, $response['meta']['pages']);

        $responsePage2 = $this->jsonRequest(
            'GET',
            "/api/v1/vaults/{$ctx['vaultId']}/items?page=2&limit=2",
            [],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertCount(2, $responsePage2['data']);
        $this->assertEquals(2, $responsePage2['meta']['page']);
    }

    public function testBulkDeleteItems(): void
    {
        $ctx = $this->setupUserWithVault('itembulkdelete@example.com');

        $id1 = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $this->validItemPayload(), $ctx['token'])['data']['id'];
        $payload2 = $this->validItemPayload();
        $payload2['titleHint'] = 'Item 2';
        $id2 = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $payload2, $ctx['token'])['data']['id'];
        $payload3 = $this->validItemPayload();
        $payload3['titleHint'] = 'Item 3';
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $payload3, $ctx['token']);

        $response = $this->jsonRequest(
            'DELETE',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            ['ids' => [$id1, $id2]],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals(2, $response['data']['deleted']);

        $listResponse = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/items", [], $ctx['token']);
        $this->assertEquals(1, $listResponse['meta']['total']);
    }

    public function testBulkDeleteRequiresAuth(): void
    {
        $this->jsonRequest('DELETE', '/api/v1/vaults/some-id/items', ['ids' => ['00000000-0000-0000-0000-000000000001']]);
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testBulkDeleteValidationFails(): void
    {
        $ctx = $this->setupUserWithVault('itembulkvalidate@example.com');

        $response = $this->jsonRequest(
            'DELETE',
            "/api/v1/vaults/{$ctx['vaultId']}/items",
            ['ids' => []],
            $ctx['token']
        );

        $this->assertContains($this->getStatusCode(), [400, 422]);
    }

    public function testBulkDeleteIgnoresItemsFromOtherVault(): void
    {
        $ctx1 = $this->setupUserWithVault('itembulkother1@example.com');
        $ctx2 = $this->setupUserWithVault('itembulkother2@example.com');

        $id = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx1['vaultId']}/items", $this->validItemPayload(), $ctx1['token'])['data']['id'];

        // Пользователь 2 пытается удалить элемент из хранилища пользователя 1
        $response = $this->jsonRequest(
            'DELETE',
            "/api/v1/vaults/{$ctx2['vaultId']}/items",
            ['ids' => [$id]],
            $ctx2['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals(0, $response['data']['deleted']);

        // Элемент у пользователя 1 остался
        $listResponse = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx1['vaultId']}/items", [], $ctx1['token']);
        $this->assertEquals(1, $listResponse['meta']['total']);
    }

    public function testBulkMoveItems(): void
    {
        $ctx = $this->setupUserWithVault('itembulkmove@example.com');

        $categoryResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/categories",
            ['name' => 'Work', 'color' => '#ff0000'],
            $ctx['token']
        );
        $categoryId = $categoryResponse['data']['id'];

        $id1 = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $this->validItemPayload(), $ctx['token'])['data']['id'];
        $payload2 = $this->validItemPayload();
        $payload2['titleHint'] = 'Item 2';
        $id2 = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $payload2, $ctx['token'])['data']['id'];

        $response = $this->jsonRequest(
            'PATCH',
            "/api/v1/vaults/{$ctx['vaultId']}/items/move",
            ['ids' => [$id1, $id2], 'categoryId' => $categoryId],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals(2, $response['data']['moved']);

        $item1 = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/items/{$id1}", [], $ctx['token']);
        $this->assertEquals($categoryId, $item1['data']['categoryId']);
    }

    public function testBulkMoveToNullCategory(): void
    {
        $ctx = $this->setupUserWithVault('itembulkmovenull@example.com');

        $categoryResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/categories",
            ['name' => 'Work', 'color' => '#ff0000'],
            $ctx['token']
        );
        $categoryId = $categoryResponse['data']['id'];

        $payload = $this->validItemPayload();
        $payload['categoryId'] = $categoryId;
        $id1 = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $payload, $ctx['token'])['data']['id'];

        // Снять категорию
        $response = $this->jsonRequest(
            'PATCH',
            "/api/v1/vaults/{$ctx['vaultId']}/items/move",
            ['ids' => [$id1]],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertEquals(1, $response['data']['moved']);

        $item = $this->jsonRequest('GET', "/api/v1/vaults/{$ctx['vaultId']}/items/{$id1}", [], $ctx['token']);
        $this->assertNull($item['data']['categoryId']);
    }

    public function testBulkMoveRequiresAuth(): void
    {
        $this->jsonRequest('PATCH', '/api/v1/vaults/some-id/items/move', ['ids' => ['00000000-0000-0000-0000-000000000001']]);
        $this->assertEquals(401, $this->getStatusCode());
    }

    public function testBulkMoveToInvalidCategory(): void
    {
        $ctx = $this->setupUserWithVault('itembulkmoveinvalid@example.com');

        $id1 = $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $this->validItemPayload(), $ctx['token'])['data']['id'];

        $fakeCategoryId = '00000000-0000-0000-0000-000000000000';
        $response = $this->jsonRequest(
            'PATCH',
            "/api/v1/vaults/{$ctx['vaultId']}/items/move",
            ['ids' => [$id1], 'categoryId' => $fakeCategoryId],
            $ctx['token']
        );

        $this->assertEquals(422, $this->getStatusCode());
    }

    public function testListWithCategoryFilter(): void
    {
        $ctx = $this->setupUserWithVault('itemcatfilter@example.com');

        $categoryResponse = $this->jsonRequest(
            'POST',
            "/api/v1/vaults/{$ctx['vaultId']}/categories",
            ['name' => 'Work', 'color' => '#ff0000'],
            $ctx['token']
        );
        $categoryId = $categoryResponse['data']['id'];

        $payloadWithCat = $this->validItemPayload();
        $payloadWithCat['titleHint'] = 'Work Item';
        $payloadWithCat['categoryId'] = $categoryId;
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $payloadWithCat, $ctx['token']);

        $payloadNoCat = $this->validItemPayload();
        $payloadNoCat['titleHint'] = 'No Category Item';
        $this->jsonRequest('POST', "/api/v1/vaults/{$ctx['vaultId']}/items", $payloadNoCat, $ctx['token']);

        $response = $this->jsonRequest(
            'GET',
            "/api/v1/vaults/{$ctx['vaultId']}/items?category={$categoryId}",
            [],
            $ctx['token']
        );

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertCount(1, $response['data']);
        $this->assertEquals($categoryId, $response['data'][0]['categoryId']);
        $this->assertEquals(1, $response['meta']['total']);
    }
}
