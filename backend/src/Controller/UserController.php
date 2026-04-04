<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\User\ChangeMasterPasswordDTO;
use App\DTO\User\EmailChangeRequestDTO;
use App\DTO\User\ReEncryptedItemDTO;
use App\Entity\User;
use App\Service\Auth\RefreshTokenService;
use App\Service\Security\SecurityLogService;
use App\Service\Security\SecurityNotificationService;
use App\Service\User\EmailChangeService;
use App\Service\User\MasterPasswordService;
use App\Service\User\UserService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/user')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly UserService $userService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly MasterPasswordService $masterPasswordService,
        private readonly SecurityLogService $securityLogService,
        private readonly SecurityNotificationService $securityNotificationService,
        private readonly EmailChangeService $emailChangeService,
        private readonly ValidatorInterface $validator,
        #[Autowire(env: 'bool:RATE_LIMITER_ENABLED')]
        private readonly bool $rateLimiterEnabled = true,
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
            'is2faEnabled' => $user->is2faEnabled(),
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
    public function delete(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Сохраняем email до удаления — после flush сущность уже недоступна
        $email = $user->getEmail();

        // Уведомляем до удаления, чтобы email попал в очередь до потери данных
        $this->securityNotificationService->notifyAccountDeleted($email, $request);

        $this->userService->delete($user);
        return $this->noContentResponse();
    }

    #[Route('/master-password', methods: ['POST'])]
    public function changeMasterPassword(
        Request $request,
        RateLimiterFactory $userMasterPasswordLimiter,
    ): JsonResponse {
        if ($this->rateLimiterEnabled) {
            $limiter = $userMasterPasswordLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                return $this->errorResponse('Too many requests', 429);
            }
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = new ChangeMasterPasswordDTO();
        $dto->currentMasterPasswordHash = $data['currentMasterPasswordHash'] ?? '';
        $dto->newMasterPasswordHash = $data['newMasterPasswordHash'] ?? '';
        $dto->newSalt = $data['newSalt'] ?? '';
        $dto->newKdfParams = $data['newKdfParams'] ?? [];
        $dto->items = array_map(static function (array $i): ReEncryptedItemDTO {
            $itemDto = new ReEncryptedItemDTO();
            $itemDto->id = $i['id'] ?? '';
            $itemDto->encryptedData = $i['encryptedData'] ?? '';
            $itemDto->iv = $i['iv'] ?? '';
            $itemDto->authTag = $i['authTag'] ?? '';
            return $itemDto;
        }, $data['items'] ?? []);

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $violations = [];
            foreach ($errors as $error) {
                $violations[] = [
                    'property' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }
            return $this->validationErrorResponse($violations);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $tokens = $this->masterPasswordService->changeMasterPassword($user, $dto, $request);
            return $this->successResponse($tokens);
        } catch (AuthenticationException) {
            return $this->errorResponse('Invalid current master password', 401);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    #[Route('/email-change', methods: ['POST'])]
    public function requestEmailChange(
        Request $request,
        RateLimiterFactory $userEmailChangeLimiter,
    ): JsonResponse {
        if ($this->rateLimiterEnabled) {
            $limiter = $userEmailChangeLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                return $this->errorResponse('Too many requests', 429);
            }
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = new EmailChangeRequestDTO();
        $dto->newEmail = $data['newEmail'] ?? '';

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $violations = [];
            foreach ($errors as $error) {
                $violations[] = [
                    'property' => $error->getPropertyPath(),
                    'message'  => $error->getMessage(),
                ];
            }
            return $this->validationErrorResponse($violations);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->emailChangeService->requestEmailChange($user, $dto->newEmail);
        } catch (\InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'already in use') ? 409 : 400;
            return $this->errorResponse($e->getMessage(), $statusCode);
        }

        $this->securityLogService->log('user.email_change_requested', $user, $request, [
            'new_email' => $dto->newEmail,
        ]);

        return $this->successResponse(['message' => 'Confirmation email sent to the new address'], [], 202);
    }

    #[Route('/sessions', methods: ['GET'])]
    public function getSessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $currentIp = $request->getClientIp();
        $currentDeviceInfo = $request->headers->get('User-Agent');

        $tokens = $this->refreshTokenService->getActiveSessions($user);

        $sessions = array_map(static function ($token) use ($currentIp, $currentDeviceInfo): array {
            return [
                'id' => $token->getId()?->toRfc4122(),
                'ipAddress' => $token->getIpAddress(),
                'deviceInfo' => $token->getDeviceInfo(),
                'createdAt' => $token->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'expiresAt' => $token->getExpiresAt()->format(\DateTimeInterface::ATOM),
                'isCurrent' => $token->getIpAddress() === $currentIp && $token->getDeviceInfo() === $currentDeviceInfo,
            ];
        }, $tokens);

        return $this->successResponse($sessions);
    }

    #[Route('/sessions/{id}', methods: ['DELETE'])]
    public function deleteSession(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $revoked = $this->refreshTokenService->revokeSessionById($id, $user);

        if (!$revoked) {
            return $this->errorResponse('Session not found', 404);
        }

        $this->securityLogService->log('session.revoked', $user, $request, ['session_id' => $id]);

        return $this->noContentResponse();
    }
}
