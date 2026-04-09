<?php

declare(strict_types=1);

namespace App\Service\Vault;

use App\DTO\Category\CreateCategoryDTO;
use App\DTO\Vault\CreateVaultItemDTO;
use App\Entity\Vault;
use App\Entity\VaultItem;
use App\Repository\CategoryRepository;
use App\Repository\VaultItemRepository;
use App\Service\Category\CategoryService;

class VaultExportService
{
    public function __construct(
        private readonly VaultItemRepository $itemRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly CategoryService $categoryService,
        private readonly VaultItemService $itemService,
    ) {}

    public function export(Vault $vault): array
    {
        $categories = $this->categoryRepository->findByVault($vault);
        $items = $this->itemRepository->findByVault($vault);

        return [
            'version' => '1.0',
            'exportedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'vault' => ['name' => $vault->getName()],
            'categories' => array_map(fn ($c) => [
                'id' => $c->getId()?->toRfc4122(),
                'name' => $c->getName(),
                'color' => $c->getColor(),
                'icon' => $c->getIcon(),
            ], $categories),
            'items' => array_map(fn ($item) => [
                'encryptedData' => $item->getEncryptedData(),
                'iv' => $item->getIv(),
                'authTag' => $item->getAuthTag(),
                'itemType' => $item->getItemType(),
                'titleHint' => $item->getTitleHint(),
                'isFavorite' => $item->isFavorite(),
                'categoryId' => $item->getCategory()?->getId()?->toRfc4122(),
            ], $items),
        ];
    }

    /**
     * @return array{categories: int, items: int}
     */
    public function import(Vault $vault, array $categories, array $items): array
    {
        // Создаём категории и строим карту старый ID → новый объект
        $categoryMap = [];
        $categoriesCreated = 0;

        foreach ($categories as $catData) {
            if (!is_array($catData) || empty($catData['name'])) {
                continue;
            }

            $dto = new CreateCategoryDTO();
            $dto->name = mb_substr((string) $catData['name'], 0, 255);
            $dto->color = isset($catData['color']) && is_string($catData['color']) ? $catData['color'] : null;
            $dto->icon = isset($catData['icon']) && is_string($catData['icon']) ? mb_substr($catData['icon'], 0, 50) : null;

            $newCategory = $this->categoryService->create($vault, $dto);

            if (isset($catData['id']) && is_string($catData['id'])) {
                $categoryMap[$catData['id']] = $newCategory->getId()?->toRfc4122();
            }

            ++$categoriesCreated;
        }

        $allowedTypes = [VaultItem::TYPE_LOGIN, VaultItem::TYPE_NOTE, VaultItem::TYPE_CARD, VaultItem::TYPE_IDENTITY];
        $itemsCreated = 0;

        foreach ($items as $itemData) {
            if (!is_array($itemData)) {
                continue;
            }

            // Пропускаем записи с отсутствующими обязательными полями
            $encryptedData = $itemData['encryptedData'] ?? null;
            $iv = $itemData['iv'] ?? null;
            $authTag = $itemData['authTag'] ?? null;
            $itemType = $itemData['itemType'] ?? null;
            $titleHint = $itemData['titleHint'] ?? null;

            if (
                !is_string($encryptedData) || $encryptedData === ''
                || !is_string($iv) || strlen($iv) < 16 || strlen($iv) > 24
                || !is_string($authTag) || strlen($authTag) < 16 || strlen($authTag) > 24
                || !is_string($itemType) || !in_array($itemType, $allowedTypes, true)
                || !is_string($titleHint) || $titleHint === ''
            ) {
                continue;
            }

            $dto = new CreateVaultItemDTO();
            $dto->encryptedData = $encryptedData;
            $dto->iv = $iv;
            $dto->authTag = $authTag;
            $dto->itemType = $itemType;
            $dto->titleHint = mb_substr($titleHint, 0, 255);
            $dto->isFavorite = !empty($itemData['isFavorite']);

            // Маппим старый categoryId на новый
            $oldCategoryId = $itemData['categoryId'] ?? null;
            if (is_string($oldCategoryId) && isset($categoryMap[$oldCategoryId])) {
                $dto->categoryId = $categoryMap[$oldCategoryId];
            }

            $this->itemService->create($vault, $dto);
            ++$itemsCreated;
        }

        return ['categories' => $categoriesCreated, 'items' => $itemsCreated];
    }
}
