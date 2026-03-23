<?php

declare(strict_types=1);

namespace App\Service\Category;

use App\DTO\Category\CreateCategoryDTO;
use App\Entity\Category;
use App\Entity\Vault;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function create(Vault $vault, CreateCategoryDTO $dto): Category
    {
        $category = new Category();
        $category->setVault($vault);
        $category->setName($dto->name);
        $category->setColor($dto->color);
        $category->setIcon($dto->icon);

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    public function findByVault(Vault $vault): array
    {
        return $this->repository->findByVault($vault);
    }

    public function findByIdAndVault(string $id, Vault $vault): Category
    {
        $category = $this->repository->find($id);

        if ($category === null || $category->getVault()->getId()?->toRfc4122() !== $vault->getId()?->toRfc4122()) {
            throw new \RuntimeException('Category not found');
        }

        return $category;
    }

    public function update(Category $category, CreateCategoryDTO $dto): Category
    {
        $category->setName($dto->name);
        $category->setColor($dto->color);
        $category->setIcon($dto->icon);

        $this->em->flush();
        return $category;
    }

    public function delete(Category $category): void
    {
        $this->em->remove($category);
        $this->em->flush();
    }
}
