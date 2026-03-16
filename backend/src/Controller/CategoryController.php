<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Category\CreateCategoryDTO;
use App\Entity\User;
use App\Service\Category\CategoryService;
use App\Service\Vault\VaultService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/vaults/{vaultId}/categories')]
#[IsGranted('ROLE_USER')]
class CategoryController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly VaultService $vaultService,
        private readonly CategoryService $categoryService,
    ) {}

    private function formatCategory(\App\Entity\Category $cat): array
    {
        return [
            'id' => $cat->getId()?->toRfc4122(),
            'name' => $cat->getName(),
            'color' => $cat->getColor(),
            'icon' => $cat->getIcon(),
            'createdAt' => $cat->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    #[Route('', methods: ['GET'])]
    public function list(string $vaultId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($vaultId, $user);
        } catch (\RuntimeException) {
            return $this->errorResponse('Vault not found', 404);
        }

        $categories = $this->categoryService->findByVault($vault);
        return $this->successResponse(array_map($this->formatCategory(...), $categories));
    }

    #[Route('', methods: ['POST'])]
    public function create(string $vaultId, #[MapRequestPayload] CreateCategoryDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($vaultId, $user);
        } catch (\RuntimeException) {
            return $this->errorResponse('Vault not found', 404);
        }

        $category = $this->categoryService->create($vault, $dto);
        return $this->createdResponse($this->formatCategory($category));
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $vaultId, string $id, #[MapRequestPayload] CreateCategoryDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($vaultId, $user);
            $category = $this->categoryService->findByIdAndVault($id, $vault);
        } catch (\RuntimeException) {
            return $this->errorResponse('Category not found', 404);
        }

        $category = $this->categoryService->update($category, $dto);
        return $this->successResponse($this->formatCategory($category));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $vaultId, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($vaultId, $user);
            $category = $this->categoryService->findByIdAndVault($id, $vault);
        } catch (\RuntimeException) {
            return $this->errorResponse('Category not found', 404);
        }

        $this->categoryService->delete($category);
        return $this->noContentResponse();
    }
}
