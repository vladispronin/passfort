<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Vault\ImportVaultDTO;
use App\Entity\User;
use App\Service\Vault\VaultExportService;
use App\Service\Vault\VaultService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/vaults/{id}')]
#[IsGranted('ROLE_USER')]
class VaultExportController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly VaultService $vaultService,
        private readonly VaultExportService $exportService,
    ) {}

    #[Route('/export', methods: ['GET'])]
    public function export(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($id, $user);
        } catch (\RuntimeException) {
            return $this->errorResponse('Vault not found', 404);
        }

        return $this->successResponse($this->exportService->export($vault));
    }

    #[Route('/import', methods: ['POST'])]
    public function import(string $id, #[MapRequestPayload] ImportVaultDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($id, $user);
        } catch (\RuntimeException) {
            return $this->errorResponse('Vault not found', 404);
        }

        $imported = $this->exportService->import($vault, $dto->categories, $dto->items ?? []);

        return $this->createdResponse(['imported' => $imported]);
    }
}
