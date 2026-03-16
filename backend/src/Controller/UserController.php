<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\Auth\RefreshTokenService;
use App\Service\User\UserService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/user')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UserService $userService,
        private readonly RefreshTokenService $refreshTokenService,
    ) {}

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->successResponse([
            'id' => $user->getId()?->toRfc4122(),
            'email' => $user->getEmail(),
            'kdfParams' => $user->getKdfParams(),
            'salt' => $user->getSalt(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/me', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (isset($data['email'])) {
            try {
                $this->userService->updateEmail($user, $data['email']);
            } catch (\InvalidArgumentException $e) {
                return $this->errorResponse($e->getMessage(), 409);
            }
        }

        return $this->successResponse([
            'id' => $user->getId()?->toRfc4122(),
            'email' => $user->getEmail(),
        ]);
    }

    #[Route('/me', methods: ['DELETE'])]
    public function delete(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->userService->delete($user);
        return $this->noContentResponse();
    }
}
