<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Auth\LoginDTO;
use App\DTO\Auth\RegisterDTO;
use App\Service\Auth\AuthService;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TokenService;
use App\Service\Security\SecurityLogService;
use App\Trait\ApiResponseTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        $limiter = $authRegisterLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->errorResponse('Too many requests', 429);
        }

        try {
            $user = $this->authService->register($dto);
            $this->securityLogService->log('user.register', $user, $request);

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
        $limiter = $authLoginLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->errorResponse('Too many requests', 429);
        }

        try {
            $tokens = $this->authService->login($dto, $request);
            $this->securityLogService->log('user.login', null, $request, ['email' => $dto->email]);

            return $this->successResponse($tokens);
        } catch (AuthenticationException) {
            $this->securityLogService->log('user.login.failed', null, $request, ['email' => $dto->email]);
            return $this->errorResponse('Invalid credentials', 401);
        }
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if ($user !== null) {
            $this->refreshTokenService->revokeAllUserTokens($user);
            $this->securityLogService->log('user.logout', $user, $request);
        }
        return $this->noContentResponse();
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        RateLimiterFactory $authRefreshLimiter,
    ): JsonResponse {
        $limiter = $authRefreshLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->errorResponse('Too many requests', 429);
        }

        $data = json_decode($request->getContent(), true);
        $rawToken = $data['refresh_token'] ?? '';

        $result = $this->refreshTokenService->rotateRefreshToken($rawToken, $request);
        if ($result === null) {
            return $this->errorResponse('Invalid or expired refresh token', 401);
        }

        $accessToken = $this->tokenService->createAccessToken($result['user']);

        return $this->successResponse([
            'access_token' => $accessToken,
            'refresh_token' => $result['refreshToken'],
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ]);
    }
}
