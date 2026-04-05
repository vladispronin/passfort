<?php

declare(strict_types=1);

namespace App\Service\Vault;

use App\DTO\Vault\CreateVaultItemDTO;
use App\Entity\Vault;
use App\Entity\VaultItem;
use App\Repository\CategoryRepository;
use App\Repository\VaultItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class VaultItemService
{
    public function __construct(
        private readonly VaultItemRepository $repository,
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function create(Vault $vault, CreateVaultItemDTO $dto): VaultItem
    {
        $item = new VaultItem();
        $item->setVault($vault);
        $item->setEncryptedData($dto->encryptedData);
        $item->setIv($dto->iv);
        $item->setAuthTag($dto->authTag);
        $item->setItemType($dto->itemType);
        $item->setTitleHint($dto->titleHint);
        $item->setIsFavorite($dto->isFavorite);

        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if ($category !== null && $category->getVault()->getId() === $vault->getId()) {
                $item->setCategory($category);
            }
        }

        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    public function findByVault(Vault $vault): array
    {
        return $this->repository->findByVault($vault);
    }

    /**
     * @return array{items: \App\Entity\VaultItem[], total: int}
     */
    public function findByVaultFiltered(Vault $vault, array $filters, int $page, int $limit): array
    {
        return $this->repository->findByVaultWithFilters($vault, $filters, $page, $limit);
    }

    public function findByIdAndVault(string $id, Vault $vault): VaultItem
    {
        $item = $this->repository->find($id);

        if ($item === null || $item->getVault()->getId()?->toRfc4122() !== $vault->getId()?->toRfc4122()) {
            throw new \RuntimeException('Item not found');
        }

        return $item;
    }

    public function update(VaultItem $item, CreateVaultItemDTO $dto): VaultItem
    {
        $item->setEncryptedData($dto->encryptedData);
        $item->setIv($dto->iv);
        $item->setAuthTag($dto->authTag);
        $item->setItemType($dto->itemType);
        $item->setTitleHint($dto->titleHint);
        $item->setIsFavorite($dto->isFavorite);

        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if ($category !== null) {
                $item->setCategory($category);
            }
        } else {
            $item->setCategory(null);
        }

        $this->em->flush();
        return $item;
    }

    public function toggleFavorite(VaultItem $item): VaultItem
    {
        $item->setIsFavorite(!$item->isFavorite());
        $this->em->flush();
        return $item;
    }

    public function delete(VaultItem $item): void
    {
        $this->em->remove($item);
        $this->em->flush();
    }
}
