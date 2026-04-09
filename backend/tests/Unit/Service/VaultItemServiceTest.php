<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\Vault\BulkDeleteDTO;
use App\DTO\Vault\BulkMoveDTO;
use App\DTO\Vault\CreateVaultItemDTO;
use App\Entity\Category;
use App\Entity\Vault;
use App\Entity\VaultItem;
use App\Repository\CategoryRepository;
use App\Repository\VaultItemRepository;
use App\Service\Vault\VaultItemService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Uid\Uuid;

class VaultItemServiceTest extends TestCase
{
    private VaultItemService $service;
    private VaultItemRepository&MockObject $repository;
    private CategoryRepository&MockObject $categoryRepository;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(VaultItemRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new VaultItemService($this->repository, $this->categoryRepository, $this->em);
    }

    public function testCreateItem(): void
    {
        $vault = new Vault();
        $dto = new CreateVaultItemDTO();
        $dto->encryptedData = base64_encode('encrypted_blob');
        $dto->iv = base64_encode(random_bytes(12));
        $dto->authTag = base64_encode(random_bytes(16));
        $dto->itemType = 'login';
        $dto->titleHint = 'Test Login';

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $item = $this->service->create($vault, $dto);

        $this->assertInstanceOf(VaultItem::class, $item);
        $this->assertEquals($dto->encryptedData, $item->getEncryptedData());
        $this->assertEquals($dto->titleHint, $item->getTitleHint());
        $this->assertEquals('login', $item->getItemType());
        $this->assertFalse($item->isFavorite());
    }

    public function testFindByVaultFiltered(): void
    {
        $vault = new Vault();
        $filters = ['type' => 'login', 'q' => 'github'];
        $expectedResult = ['items' => [], 'total' => 0];

        $this->repository
            ->expects($this->once())
            ->method('findByVaultWithFilters')
            ->with($vault, $filters, 1, 30)
            ->willReturn($expectedResult);

        $result = $this->service->findByVaultFiltered($vault, $filters, 1, 30);

        $this->assertSame($expectedResult, $result);
    }

    private function makeItem(Vault $vault): VaultItem
    {
        $item = new VaultItem();
        $item->setVault($vault);
        $item->setEncryptedData('data');
        $item->setIv('iv');
        $item->setAuthTag('tag');
        $item->setItemType('login');
        $item->setTitleHint('Test');
        return $item;
    }

    public function testBulkDeleteCallsRemoveForEachItem(): void
    {
        $vault = new Vault();
        $item1 = $this->makeItem($vault);
        $item2 = $this->makeItem($vault);

        $dto = new BulkDeleteDTO();
        $dto->ids = ['00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000002'];

        $this->repository
            ->expects($this->once())
            ->method('findByIdsAndVault')
            ->with($dto->ids, $vault)
            ->willReturn([$item1, $item2]);

        $this->em->expects($this->exactly(2))->method('remove');
        $this->em->expects($this->once())->method('flush');

        $deleted = $this->service->bulkDelete($vault, $dto);
        $this->assertEquals(2, $deleted);
    }

    public function testBulkDeleteReturnsZeroWhenNoItemsFound(): void
    {
        $vault = new Vault();
        $dto = new BulkDeleteDTO();
        $dto->ids = ['00000000-0000-0000-0000-000000000001'];

        $this->repository
            ->expects($this->once())
            ->method('findByIdsAndVault')
            ->willReturn([]);

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->once())->method('flush');

        $deleted = $this->service->bulkDelete($vault, $dto);
        $this->assertEquals(0, $deleted);
    }

    public function testBulkMoveAssignsCategoryToItems(): void
    {
        $vault = new Vault();
        $vaultId = Uuid::v4();
        $vault->setName('Test');

        $item1 = $this->makeItem($vault);
        $item2 = $this->makeItem($vault);

        $categoryId = '00000000-0000-0000-0000-000000000010';
        $category = new Category();
        $category->setVault($vault);
        $category->setName('Work');

        $dto = new BulkMoveDTO();
        $dto->ids = ['00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000002'];
        $dto->categoryId = $categoryId;

        $this->repository
            ->expects($this->once())
            ->method('findByIdsAndVault')
            ->with($dto->ids, $vault)
            ->willReturn([$item1, $item2]);

        $this->categoryRepository
            ->expects($this->once())
            ->method('find')
            ->with($categoryId)
            ->willReturn($category);

        $this->em->expects($this->once())->method('flush');

        $moved = $this->service->bulkMove($vault, $dto);
        $this->assertEquals(2, $moved);
        $this->assertSame($category, $item1->getCategory());
        $this->assertSame($category, $item2->getCategory());
    }

    public function testBulkMoveToNullCategoryRemovesCategory(): void
    {
        $vault = new Vault();
        $vault->setName('Test');

        $category = new Category();
        $category->setVault($vault);
        $category->setName('Work');

        $item = $this->makeItem($vault);
        $item->setCategory($category);

        $dto = new BulkMoveDTO();
        $dto->ids = ['00000000-0000-0000-0000-000000000001'];
        $dto->categoryId = null;

        $this->repository
            ->expects($this->once())
            ->method('findByIdsAndVault')
            ->willReturn([$item]);

        $this->categoryRepository->expects($this->never())->method('find');
        $this->em->expects($this->once())->method('flush');

        $moved = $this->service->bulkMove($vault, $dto);
        $this->assertEquals(1, $moved);
        $this->assertNull($item->getCategory());
    }

    public function testBulkMoveThrowsWhenCategoryNotFound(): void
    {
        $vault = new Vault();
        $vault->setName('Test');

        $dto = new BulkMoveDTO();
        $dto->ids = ['00000000-0000-0000-0000-000000000001'];
        $dto->categoryId = '00000000-0000-0000-0000-000000000099';

        $this->repository->method('findByIdsAndVault')->willReturn([$this->makeItem($vault)]);
        $this->categoryRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->service->bulkMove($vault, $dto);
    }

    public function testToggleFavorite(): void
    {
        $item = new VaultItem();
        // Установить vault (необходимо для entity)
        $vault = new Vault();
        $item->setVault($vault);
        $item->setEncryptedData('data');
        $item->setIv('iv');
        $item->setAuthTag('tag');
        $item->setItemType('login');
        $item->setTitleHint('Test');
        $item->setIsFavorite(false);

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->toggleFavorite($item);
        $this->assertTrue($result->isFavorite());
    }
}
