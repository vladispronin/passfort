<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Auth\LoginDTO;
use App\DTO\Auth\RegisterDTO;
use App\DTO\Auth\TwoFactorVerifyDTO;
use App\Exception\EmailNotVerifiedException;
use App\Exception\EmailVerificationException;
use App\Service\Auth\AuthService;
use App\Service\Auth\EmailVerificationService;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TokenService;
use App\Enum\SecurityLogAction;
use App\Service\Security\SecurityLogService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[Route('/api/v1/auth')]
class AuthController extends AbstractController
{
    use ApiResponseTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly TokenService $tokenService,
        private readonly SecurityLogService $securityLogService,
        private readonly EmailVerificationService $emailVerificationService,
        #[Autowire(env: 'bool:RATE_LIMITER_ENABLED')]
        private readonly bool $rateLimiterEnabled = true,
    ) {}

    #[Route('/kdf-params', methods: ['GET'])]
    public function kdfParams(Request $request): JsonResponse
    {
        $email = $request->query->get('email', '');
        $params = $this->authService->getKdfParams($email);
        return $this->successResponse($params);
    }

    #[Route('/register', methods: ['POST'])]
    public function register(
        #[MapRequestPayload] RegisterDTO $dto,
        Request $request,
        RateLimiterFactory $authRegisterLimiter,
    ): JsonResponse {
        if ($this->rateLimiterEnabled) {
            $limiter = $authRegisterLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                return $this->errorResponse('Too many requests', 429);
            }
        }

        try {
            $user = $this->authService->register($dto);
            $this->securityLogService->log(SecurityLogAction::USER_REGISTER, $user, $request);

            return $this->createdResponse([
                'id' => $user->getId()?->toRfc4122(),
                'email' => $user->getEmail(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }
    }

    #[Route('/login', methods: ['POST'])]
    public function login(
        #[MapRequestPayload] LoginDTO $dto,
        Request $request,
        RateLimiterFactory $authLoginLimiter,
    ): JsonResponse {
        if ($this->rateLimiterEnabled) {
            $limiter = $authLoginLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                return $this->errorResponse('Too many requests', 429);
            }
        }

        try {
            $result = $this->authService->login($dto, $request);

            if (isset($result['requires_2fa'])) {
                $this->securityLogService->log(SecurityLogAction::USER_LOGIN_2FA_REQUIRED, null, $request, ['email' => $dto->email]);
                return $this->successResponse([
                    'requires_2fa' => true,
                    'temp_token' => $result['temp_token'],
                ]);
            }

            $this->securityLogService->log(SecurityLogAction::USER_LOGIN, null, $request, ['email' => $dto->email]);
            return $this->successResponse($result);
        } catch (EmailNotVerifiedException) {
            $this->securityLogService->log(SecurityLogAction::USER_LOGIN_EMAIL_NOT_VERIFIED, null, $request, ['email' => $dto->email]);
            return $this->errorResponse('Please verify your email address before logging in', 403, 'EMAIL_NOT_VERIFIED');
        } catch (AuthenticationException) {
            $this->securityLogService->log(SecurityLogAction::USER_LOGIN_FAILED, null, $request, ['email' => $dto->email]);
            return $this->errorResponse('Invalid credentials', 401);
        }
    }

    #[Route('/2fa/verify', methods: ['POST'])]
    public function verifyTwoFactor(
        #[MapRequestPayload] TwoFactorVerifyDTO $dto,
        Request $request,
        RateLimiterFactory $auth2faVerifyLimiter,
    ): JsonResponse {
        if ($this->rateLimiterEnabled) {
            $limiter = $auth2faVerifyLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                return $this->errorResponse('Too many requests', 429);
            }
        }

        try {
            $tokens = $this->authService->loginWithTotp($dto->tempToken, $dto->code, $request);
            $this->securityLogService->log(SecurityLogAction::USER_LOGIN_2FA_SUCCESS, null, $request);
            return $this->successResponse($tokens);
        } catch (AuthenticationException) {
            $this->securityLogService->log(SecurityLogAction::USER_LOGIN_2FA_FAILED, null, $request);
            return $this->errorResponse('Invalid 2FA code or expired session', 401);
        }
    }

    #[Route('/verify-email', methods: ['GET'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $rawToken = $request->query->get('token', '');

        if ($rawToken === '') {
            return $this->errorResponse('Token is required', 400);
        }

        try {
            $user = $this->emailVerificationService->verifyToken($rawToken);
            $this->securityLogService->log(SecurityLogAction::USER_EMAIL_VERIFIED, $user, $request);
            return $this->successResponse(['message' => 'Email verified successfully']);
        } catch (EmailVerificationException $e) {
            return $this->errorResponse($e->getMessage(), 400, 'EMAIL_VERIFICATION_FAILED');
        }
    }

    #[Route('/resend-verification', methods: ['POST'])]
    public function resendVerification(
        Request $request,
        RateLimiterFactory $authResendVerificationLimiter,
    ): JsonResponse {
        if ($this->rateLimiterEnabled) {
            $limiter = $authResendVerificationLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                return $this->errorResponse('Too many requests', 429);
            }
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $email = $data['email'] ?? '';

        if ($email === '') {
            return $this->errorResponse('Email is required', 400);
        }

        $this->emailVerificationService->resendVerification($email);
        $this->securityLogService->log(SecurityLogAction::USER_RESEND_VERIFICATION, null, $request, ['email' => $email]);

        return $this->successResponse([
            'message' => 'If this email is registered and unverified, a verification email has been sent',
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if ($user !== null) {
            $this->refreshTokenService->revokeAllUserTokens($user);
            $this->securityLogService->log(SecurityLogAction::USER_LOGOUT, $user, $request);
        }
        return $this->noContentResponse();
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        RateLimiterFactory $authRefreshLimiter,
    ): JsonResponse {
        if ($this->rateLimiterEnabled) {
            $limiter = $authRefreshLimiter->create($request->getClientIp());
            if (!$limiter->consume()->isAccepted()) {
                return $this->errorResponse('Too many requests', 429);
            }
        }

        $data = json_decode($request->getContent(), true);
        $rawToken = $data['refresh_token'] ?? '';

        $result = $this->refreshTokenService->rotateRefreshToken($rawToken, $request);
        if ($result === null) {
            return $this->errorResponse('Invalid or expired refresh token', 401);
        }

        $accessToken = $this->tokenService->createAccessToken($result['user'], $result['sessionId']);

        return $this->successResponse([
            'access_token' => $accessToken,
            'refresh_token' => $result['refreshToken'],
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]);
    }
}
