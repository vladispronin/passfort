<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;

class RefreshTokenService
{
    private const TTL_DAYS = 30;
    private const SESSION_CACHE_PREFIX = 'session_valid_';

    public function __construct(
        private readonly RefreshTokenRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly TokenService $tokenService,
        private readonly CacheItemPoolInterface $cache,
    ) {}

    /**
     * @return array{rawToken: string, sessionId: string}
     */
    public function createRefreshToken(User $user, Request $request): array
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

        return [
            'rawToken' => $rawToken,
            'sessionId' => $refreshToken->getId()->toRfc4122(),
        ];
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
        $result = $this->createRefreshToken($user, $request);

        return [
            'user' => $user,
            'refreshToken' => $result['rawToken'],
            'sessionId' => $result['sessionId'],
        ];
    }

    public function revokeAllUserTokens(User $user): void
    {
        $this->repository->deleteByUser($user);
        $this->em->flush();
    }

    /**
     * @return RefreshToken[]
     */
    public function getActiveSessions(User $user): array
    {
        return $this->repository->findActiveByUser($user);
    }

    public function revokeSessionById(string $sessionId, User $user): bool
    {
        $token = $this->repository->findByIdAndUser($sessionId, $user);

        if ($token === null) {
            return false;
        }

        $this->em->remove($token);
        $this->em->flush();

        // Инвалидируем кэш, чтобы браузер с этой сессией получил 401 немедленно
        $this->cache->deleteItem(self::SESSION_CACHE_PREFIX . hash('sha256', $sessionId));

        return true;
    }
}
