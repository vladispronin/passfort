<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\EmailChangeException;
use App\Service\Security\SecurityLogService;
use App\Service\Security\SecurityNotificationService;
use App\Service\User\EmailChangeService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/user')]
class EmailChangeController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly EmailChangeService $emailChangeService,
        private readonly SecurityLogService $securityLogService,
        private readonly SecurityNotificationService $securityNotificationService,
    ) {}

    #[Route('/email-change/confirm', methods: ['GET'])]
    public function confirmEmailChange(Request $request): JsonResponse
    {
        $rawToken = $request->query->get('token', '');

        if ($rawToken === '') {
            return $this->errorResponse('Token is required', 400);
        }

        try {
            $result   = $this->emailChangeService->confirmEmailChange($rawToken);
            $user     = $result['user'];
            $oldEmail = $result['oldEmail'];
        } catch (EmailChangeException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }

        $this->securityLogService->log('user.email_changed', $user, $request, [
            'old_email' => $oldEmail,
            'new_email' => $user->getEmail(),
        ]);

        $this->securityNotificationService->notifyEmailChanged($oldEmail, $user->getEmail(), $request);

        return $this->successResponse(['message' => 'Email successfully updated']);
    }
}
