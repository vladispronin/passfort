<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Auth\TokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Entity\User;

class TokenServiceTest extends TestCase
{
    private TokenService $tokenService;
    private JWTTokenManagerInterface&MockObject $jwtManager;

    protected function setUp(): void
    {
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->tokenService = new TokenService($this->jwtManager);
    }

    public function testGenerateRefreshToken(): void
    {
        $token = $this->tokenService->generateRefreshToken();
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testHashRefreshToken(): void
    {
        $token = 'test_token_value';
        $hash = $this->tokenService->hashRefreshToken($token);
        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash)); // SHA-256 = 64 hex chars
        // Детерминированный хэш
        $this->assertEquals($hash, $this->tokenService->hashRefreshToken($token));
    }

    public function testHashRefreshTokenIsDifferentForDifferentInputs(): void
    {
        $hash1 = $this->tokenService->hashRefreshToken('token1');
        $hash2 = $this->tokenService->hashRefreshToken('token2');
        $this->assertNotEquals($hash1, $hash2);
    }

    public function testCreateAccessToken(): void
    {
        $user = new User();
        $this->jwtManager->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn('jwt_token_value');

        $token = $this->tokenService->createAccessToken($user);
        $this->assertEquals('jwt_token_value', $token);
    }
}
