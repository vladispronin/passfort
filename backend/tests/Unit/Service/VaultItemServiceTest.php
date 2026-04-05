<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\Vault\CreateVaultItemDTO;
use App\Entity\Vault;
use App\Entity\VaultItem;
use App\Repository\CategoryRepository;
use App\Repository\VaultItemRepository;
use App\Service\Vault\VaultItemService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

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
