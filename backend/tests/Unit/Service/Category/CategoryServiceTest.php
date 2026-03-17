<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Category;

use App\DTO\Category\CreateCategoryDTO;
use App\Entity\Category;
use App\Entity\Vault;
use App\Repository\CategoryRepository;
use App\Service\Category\CategoryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class CategoryServiceTest extends TestCase
{
    private CategoryService $service;
    private CategoryRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CategoryRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new CategoryService($this->repository, $this->em);
    }

    private function makeDto(string $name = 'Work', ?string $color = '#ff0000', ?string $icon = 'briefcase'): CreateCategoryDTO
    {
        $dto = new CreateCategoryDTO();
        $dto->name = $name;
        $dto->color = $color;
        $dto->icon = $icon;
        return $dto;
    }

    public function testCreate(): void
    {
        $vault = new Vault();
        $dto = $this->makeDto();

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->create($vault, $dto);

        $this->assertInstanceOf(Category::class, $result);
        $this->assertEquals('Work', $result->getName());
        $this->assertEquals('#ff0000', $result->getColor());
        $this->assertEquals('briefcase', $result->getIcon());
        $this->assertSame($vault, $result->getVault());
    }

    public function testFindByVault(): void
    {
        $vault = new Vault();
        $categories = [new Category(), new Category()];

        $this->repository->expects($this->once())
            ->method('findByVault')
            ->with($vault)
            ->willReturn($categories);

        $result = $this->service->findByVault($vault);
        $this->assertCount(2, $result);
    }

    public function testFindByIdAndVaultSuccess(): void
    {
        // Создаём vault с UUID через рефлексию
        $vaultId = Uuid::v4();
        $vault = new Vault();
        $ref = new \ReflectionProperty(Vault::class, 'id');
        $ref->setValue($vault, $vaultId);

        $category = new Category();
        $category->setVault($vault);

        $this->repository->expects($this->once())
            ->method('find')
            ->with('some-uuid')
            ->willReturn($category);

        $result = $this->service->findByIdAndVault('some-uuid', $vault);
        $this->assertSame($category, $result);
    }

    public function testFindByIdAndVaultNotFound(): void
    {
        $vault = new Vault();
        $vaultId = Uuid::v4();
        $ref = new \ReflectionProperty(Vault::class, 'id');
        $ref->setValue($vault, $vaultId);

        $this->repository->expects($this->once())
            ->method('find')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Category not found');

        $this->service->findByIdAndVault('non-existent', $vault);
    }

    public function testFindByIdAndVaultWrongVault(): void
    {
        $vault1 = new Vault();
        $vault1Id = Uuid::v4();
        $ref1 = new \ReflectionProperty(Vault::class, 'id');
        $ref1->setValue($vault1, $vault1Id);

        $vault2 = new Vault();
        $vault2Id = Uuid::v4();
        $ref2 = new \ReflectionProperty(Vault::class, 'id');
        $ref2->setValue($vault2, $vault2Id);

        $category = new Category();
        $category->setVault($vault2); // категория принадлежит vault2

        $this->repository->expects($this->once())
            ->method('find')
            ->willReturn($category);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Category not found');

        // Запрашиваем из vault1 — должна быть ошибка
        $this->service->findByIdAndVault('some-id', $vault1);
    }

    public function testUpdate(): void
    {
        $vault = new Vault();
        $category = new Category();
        $category->setVault($vault);
        $category->setName('Old Name');

        $dto = $this->makeDto('New Name', '#00ff00', 'star');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->update($category, $dto);

        $this->assertEquals('New Name', $result->getName());
        $this->assertEquals('#00ff00', $result->getColor());
        $this->assertEquals('star', $result->getIcon());
    }

    public function testDelete(): void
    {
        $category = new Category();

        $this->em->expects($this->once())->method('remove')->with($category);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete($category);
    }
}
