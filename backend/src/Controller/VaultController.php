<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Vault\CreateVaultDTO;
use App\Entity\User;
use App\Service\Vault\VaultService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/vaults')]
#[IsGranted('ROLE_USER')]
class VaultController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly VaultService $vaultService,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $vaults = $this->vaultService->findByUser($user);

        return $this->successResponse(array_map(fn($v) => [
            'id' => $v->getId()?->toRfc4122(),
            'name' => $v->getName(),
            'createdAt' => $v->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $v->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ], $vaults));
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateVaultDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $vault = $this->vaultService->create($user, $dto->name);

        return $this->createdResponse([
            'id' => $vault->getId()?->toRfc4122(),
            'name' => $vault->getName(),
            'createdAt' => $vault->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($id, $user);
        } catch (\RuntimeException) {
            return $this->errorResponse('Vault not found', 404);
        }

        return $this->successResponse([
            'id' => $vault->getId()?->toRfc4122(),
            'name' => $vault->getName(),
            'createdAt' => $vault->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $vault->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $id, #[MapRequestPayload] CreateVaultDTO $dto): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($id, $user);
        } catch (\RuntimeException) {
            return $this->errorResponse('Vault not found', 404);
        }

        $vault = $this->vaultService->update($vault, $dto->name);

        return $this->successResponse([
            'id' => $vault->getId()?->toRfc4122(),
            'name' => $vault->getName(),
            'updatedAt' => $vault->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $vault = $this->vaultService->findByIdAndUser($id, $user);
        } catch (\RuntimeException) {
            return $this->errorResponse('Vault not found', 404);
        }

        $this->vaultService->delete($vault);
        return $this->noContentResponse();
    }
}
