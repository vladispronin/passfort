<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\RefreshToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class RefreshTokenTest extends TestCase
{
    public function testSetAndGetUser(): void
    {
        $user = new User();
        $token = new RefreshToken();
        $token->setUser($user);

        $this->assertSame($user, $token->getUser());
    }

    public function testSetAndGetTokenHash(): void
    {
        $token = new RefreshToken();
        $token->setTokenHash('abc123hash');

        $this->assertEquals('abc123hash', $token->getTokenHash());
    }

    public function testSetAndGetDeviceInfo(): void
    {
        $token = new RefreshToken();
        $token->setDeviceInfo('Mozilla/5.0');

        $this->assertEquals('Mozilla/5.0', $token->getDeviceInfo());
    }

    public function testDeviceInfoNullable(): void
    {
        $token = new RefreshToken();
        $token->setDeviceInfo(null);

        $this->assertNull($token->getDeviceInfo());
    }

    public function testSetAndGetIpAddress(): void
    {
        $token = new RefreshToken();
        $token->setIpAddress('127.0.0.1');

        $this->assertEquals('127.0.0.1', $token->getIpAddress());
    }

    public function testIpAddressNullable(): void
    {
        $token = new RefreshToken();
        $token->setIpAddress(null);

        $this->assertNull($token->getIpAddress());
    }

    public function testIsExpiredWhenExpired(): void
    {
        $token = new RefreshToken();
        // Устанавливаем время в прошлом
        $token->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $this->assertTrue($token->isExpired());
    }

    public function testIsNotExpiredWhenValid(): void
    {
        $token = new RefreshToken();
        // Устанавливаем время в будущем
        $token->setExpiresAt(new \DateTimeImmutable('+30 days'));

        $this->assertFalse($token->isExpired());
    }

    public function testSetAndGetExpiresAt(): void
    {
        $token = new RefreshToken();
        $expiry = new \DateTimeImmutable('+7 days');
        $token->setExpiresAt($expiry);

        $this->assertSame($expiry, $token->getExpiresAt());
    }
}
