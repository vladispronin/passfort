<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;

class RefreshTokenServiceTest extends TestCase
{
    private RefreshTokenService $service;
    private RefreshTokenRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;
    private TokenService&MockObject $tokenService;
    private CacheItemPoolInterface&MockObject $cache;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RefreshTokenRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->service = new RefreshTokenService($this->repository, $this->em, $this->tokenService, $this->cache);
    }

    public function testCreateRefreshToken(): void
    {
        $user = new User();
        $request = Request::create('/');

        $this->tokenService->expects($this->once())
            ->method('generateRefreshToken')
            ->willReturn('raw_token_value');

        $this->tokenService->expects($this->once())
            ->method('hashRefreshToken')
            ->with('raw_token_value')
            ->willReturn('hashed_token_value');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createRefreshToken($user, $request);

        // Должны получить обратно raw (не хэшированный) токен и sessionId
        $this->assertEquals('raw_token_value', $result['rawToken']);
        $this->assertArrayHasKey('sessionId', $result);
    }

    public function testRotateRefreshTokenSuccess(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);
        $refreshToken->setTokenHash('hashed_old_token');
        $refreshToken->setExpiresAt(new \DateTimeImmutable('+30 days'));

        $request = Request::create('/');

        $this->tokenService->expects($this->exactly(2))
            ->method('hashRefreshToken')
            ->willReturnMap([
                ['old_raw_token', 'hashed_old_token'],
                ['new_raw_token', 'hashed_new_token'],
            ]);

        $this->tokenService->expects($this->once())
            ->method('generateRefreshToken')
            ->willReturn('new_raw_token');

        $this->repository->expects($this->once())
            ->method('findByTokenHash')
            ->with('hashed_old_token')
            ->willReturn($refreshToken);

        $this->em->expects($this->once())->method('remove')->with($refreshToken);
        // flush вызывается 1 раз внутри createRefreshToken, remove не делает flush сразу
        $this->em->expects($this->once())->method('flush');
        $this->em->expects($this->once())->method('persist');

        $result = $this->service->rotateRefreshToken('old_raw_token', $request);

        $this->assertNotNull($result);
        $this->assertSame($user, $result['user']);
        $this->assertEquals('new_raw_token', $result['refreshToken']);
    }

    public function testRotateRefreshTokenNotFound(): void
    {
        $request = Request::create('/');

        $this->tokenService->expects($this->once())
            ->method('hashRefreshToken')
            ->willReturn('hashed_token');

        $this->repository->expects($this->once())
            ->method('findByTokenHash')
            ->willReturn(null);

        $result = $this->service->rotateRefreshToken('invalid_token', $request);

        $this->assertNull($result);
    }

    public function testRotateRefreshTokenExpired(): void
    {
        $user = new User();
        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);
        $refreshToken->setTokenHash('hashed_token');
        $refreshToken->setExpiresAt(new \DateTimeImmutable('-1 day')); // истёкший

        $request = Request::create('/');

        $this->tokenService->expects($this->once())
            ->method('hashRefreshToken')
            ->willReturn('hashed_token');

        $this->repository->expects($this->once())
            ->method('findByTokenHash')
            ->willReturn($refreshToken);

        $result = $this->service->rotateRefreshToken('expired_token', $request);

        $this->assertNull($result);
    }

    public function testRevokeAllUserTokens(): void
    {
        $user = new User();

        $this->repository->expects($this->once())
            ->method('deleteByUser')
            ->with($user);

        $this->em->expects($this->once())->method('flush');

        $this->service->revokeAllUserTokens($user);
    }

    public function testRevokeSessionByIdSuccess(): void
    {
        $user = new User();
        $token = new RefreshToken();
        $token->setUser($user);
        $token->setTokenHash('some_hash');
        $token->setExpiresAt(new \DateTimeImmutable('+30 days'));

        $this->repository->expects($this->once())
            ->method('findByIdAndUser')
            ->with('session-uuid', $user)
            ->willReturn($token);

        $this->em->expects($this->once())->method('remove')->with($token);
        $this->em->expects($this->once())->method('flush');

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with($this->stringContains('session_valid_'));

        $result = $this->service->revokeSessionById('session-uuid', $user);

        $this->assertTrue($result);
    }

    public function testRevokeSessionByIdNotFound(): void
    {
        $user = new User();

        $this->repository->expects($this->once())
            ->method('findByIdAndUser')
            ->with('nonexistent-id', $user)
            ->willReturn(null);

        $this->em->expects($this->never())->method('remove');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->revokeSessionById('nonexistent-id', $user);

        $this->assertFalse($result);
    }
}
