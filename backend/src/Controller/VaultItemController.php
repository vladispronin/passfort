<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Vault\CreateVaultItemDTO;
use App\Entity\User;
use App\Service\Vault\VaultItemService;
use App\Service\Vault\VaultService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/vaults/{vaultId}/items')]
#[IsGranted('ROLE_USER')]
class VaultItemController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly VaultService $vaultService,
        private readonly VaultItemService $itemService,
    ) {}

    private function formatItem(\App\Entity\VaultItem $item): array
    {
        return [
            'id' => $item->getId()?->toRfc4122(),
            'encryptedData' => $item->getEncryptedData(),
            'iv' => $item->getIv(),
            'authTag' => $item->getAuthTag(),
            'itemType' => $item->getItemType(),
            'titleHint' => $item->getTitleHint(),
            'isFavorite' => $item->isFavorite(),
            'categoryId' => $item->getCategory()?->getId()?->toRfc4122(),
            'createdAt' => $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $item->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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

        $items = $this->itemService->findByVault($vault);
        return $this->successResponse(array_map($this->formatItem(...), $items));
    }

    #[Route('', methods: ['POST'])]
    public function create(string $vaultId, #[MapRequestPayload] CreateVaultItemDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($vaultId, $user);
        } catch (\RuntimeException) {
            return $this->errorResponse('Vault not found', 404);
        }

        $item = $this->itemService->create($vault, $dto);
        return $this->createdResponse($this->formatItem($item));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $vaultId, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($vaultId, $user);
            $item = $this->itemService->findByIdAndVault($id, $vault);
        } catch (\RuntimeException) {
            return $this->errorResponse('Item not found', 404);
        }

        return $this->successResponse($this->formatItem($item));
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $vaultId, string $id, #[MapRequestPayload] CreateVaultItemDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($vaultId, $user);
            $item = $this->itemService->findByIdAndVault($id, $vault);
        } catch (\RuntimeException) {
            return $this->errorResponse('Item not found', 404);
        }

        $item = $this->itemService->update($item, $dto);
        return $this->successResponse($this->formatItem($item));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $vaultId, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($vaultId, $user);
            $item = $this->itemService->findByIdAndVault($id, $vault);
        } catch (\RuntimeException) {
            return $this->errorResponse('Item not found', 404);
        }

        $this->itemService->delete($item);
        return $this->noContentResponse();
    }

    #[Route('/{id}/favorite', methods: ['PATCH'])]
    public function toggleFavorite(string $vaultId, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($vaultId, $user);
            $item = $this->itemService->findByIdAndVault($id, $vault);
        } catch (\RuntimeException) {
            return $this->errorResponse('Item not found', 404);
        }

        $item = $this->itemService->toggleFavorite($item);
        return $this->successResponse($this->formatItem($item));
    }
}
