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
use Symfony\Component\HttpFoundation\Request;

class RefreshTokenServiceTest extends TestCase
{
    private RefreshTokenService $service;
    private RefreshTokenRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $em;
    private TokenService&MockObject $tokenService;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RefreshTokenRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->service = new RefreshTokenService($this->repository, $this->em, $this->tokenService);
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

        $rawToken = $this->service->createRefreshToken($user, $request);

        // Должны получить обратно raw (не хэшированный) токен
        $this->assertEquals('raw_token_value', $rawToken);
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
}
