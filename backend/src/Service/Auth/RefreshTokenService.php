<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class RefreshTokenService
{
    private const TTL_DAYS = 30;

    public function __construct(
        private readonly RefreshTokenRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly TokenService $tokenService,
    ) {}

    public function createRefreshToken(User $user, Request $request): string
    {
        $rawToken = $this->tokenService->generateRefreshToken();
        $tokenHash = $this->tokenService->hashRefreshToken($rawToken);

        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);
        $refreshToken->setTokenHash($tokenHash);
        $refreshToken->setIpAddress($request->getClientIp());
        $refreshToken->setDeviceInfo($request->headers->get('User-Agent'));
        $refreshToken->setExpiresAt(new \DateTimeImmutable(sprintf('+%d days', self::TTL_DAYS)));

        $this->em->persist($refreshToken);
        $this->em->flush();

        return $rawToken;
    }

    public function rotateRefreshToken(string $rawToken, Request $request): ?array
    {
        $tokenHash = $this->tokenService->hashRefreshToken($rawToken);
        $refreshToken = $this->repository->findByTokenHash($tokenHash);

        if ($refreshToken === null || $refreshToken->isExpired()) {
            return null;
        }

        $user = $refreshToken->getUser();

        // Удаляем старый токен (rotation)
        $this->em->remove($refreshToken);

        // Создаём новый
        $newRawToken = $this->createRefreshToken($user, $request);

        return ['user' => $user, 'refreshToken' => $newRawToken];
    }

    public function revokeAllUserTokens(User $user): void
    {
        $this->repository->deleteByUser($user);
        $this->em->flush();
    }
}
