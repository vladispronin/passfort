<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\TwoFactor\TwoFactorDisableDTO;
use App\DTO\TwoFactor\TwoFactorEnableDTO;
use App\Entity\User;
use App\Service\Auth\TotpService;
use App\Enum\SecurityLogAction;
use App\Service\Security\SecurityLogService;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/2fa')]
#[IsGranted('ROLE_USER')]
class TwoFactorController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly TotpService $totpService,
        private readonly EntityManagerInterface $em,
        private readonly SecurityLogService $securityLogService,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('/setup', methods: ['GET'])]
    public function setup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $setupData = $this->totpService->generateSetupData($user);
        $this->em->flush();

        $this->securityLogService->log(SecurityLogAction::TWO_FA_SETUP_INITIATED, $user, $request);

        return $this->successResponse($setupData);
    }

    #[Route('/enable', methods: ['POST'])]
    public function enable(
        #[MapRequestPayload] TwoFactorEnableDTO $dto,
        Request $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $backupCodes = $this->totpService->verifyAndEnable($user, $dto->code);
        if ($backupCodes === false) {
            return $this->errorResponse('Invalid TOTP code', 400);
        }

        $this->em->flush();
        $this->securityLogService->log(SecurityLogAction::TWO_FA_ENABLED, $user, $request);

        return $this->successResponse(['backup_codes' => $backupCodes]);
    }

    #[Route('/disable', methods: ['DELETE'])]
    public function disable(
        Request $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];
        $masterPasswordHash = $data['masterPasswordHash'] ?? '';

        if (empty($masterPasswordHash)) {
            return $this->validationErrorResponse([
                ['property' => 'masterPasswordHash', 'message' => 'This value should not be blank.'],
            ]);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $masterPasswordHash)) {
            return $this->errorResponse('Invalid master password', 401);
        }

        $this->totpService->disable($user);
        $this->em->flush();
        $this->securityLogService->log(SecurityLogAction::TWO_FA_DISABLED, $user, $request);

        return $this->noContentResponse();
    }

    #[Route('/backup-codes/regenerate', methods: ['POST'])]
    public function regenerateBackupCodes(
        Request $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->is2faEnabled()) {
            return $this->errorResponse('2FA is not enabled', 400);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $masterPasswordHash = $data['masterPasswordHash'] ?? '';

        if (empty($masterPasswordHash)) {
            return $this->validationErrorResponse([
                ['property' => 'masterPasswordHash', 'message' => 'This value should not be blank.'],
            ]);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $masterPasswordHash)) {
            return $this->errorResponse('Invalid master password', 401);
        }

        $rawCodes = $this->totpService->regenerateBackupCodes($user);
        $this->em->flush();
        $this->securityLogService->log(SecurityLogAction::TWO_FA_BACKUP_CODES_REGENERATED, $user, $request);

        return $this->successResponse(['backup_codes' => $rawCodes]);
    }

    #[Route('/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $backupCodes = $user->getBackupCodes();

        return $this->successResponse([
            'is_enabled' => $user->is2faEnabled(),
            'has_backup_codes' => !empty($backupCodes),
            'backup_codes_count' => count($backupCodes ?? []),
        ]);
    }
}
