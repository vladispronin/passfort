<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\Entity\User;
use App\Service\Auth\TempTokenService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Uid\Uuid;

class TempTokenServiceTest extends TestCase
{
    private TempTokenService $service;
    private CacheItemPoolInterface&MockObject $cache;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->service = new TempTokenService($this->cache);
    }

    private function makeUser(): User
    {
        $user = $this->createMock(User::class);
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');
        $user->method('getId')->willReturn($uuid);
        return $user;
    }

    public function testCreateTempTokenReturnsBinHexString(): void
    {
        $user = $this->makeUser();

        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('set')->willReturnSelf();
        $item->expects($this->once())->method('expiresAfter')->with(300)->willReturnSelf();

        $this->cache->method('getItem')->willReturn($item);
        $this->cache->expects($this->once())->method('save')->with($item);

        $token = $this->service->createTempToken($user);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testCreateTempTokenReturnsDifferentTokensEachTime(): void
    {
        $user = $this->makeUser();

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();

        $this->cache->method('getItem')->willReturn($item);
        $this->cache->method('save')->willReturn(true);

        $token1 = $this->service->createTempToken($user);
        $token2 = $this->service->createTempToken($user);

        $this->assertNotEquals($token1, $token2);
    }

    public function testGetUserIdByTempTokenReturnsUserIdWithoutDeleting(): void
    {
        $rawToken = str_repeat('a', 64);

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn([
            'user_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->cache->method('getItem')->willReturn($item);
        // Токен НЕ должен удаляться при peek
        $this->cache->expects($this->never())->method('deleteItem');

        $userId = $this->service->getUserIdByTempToken($rawToken);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $userId);
    }

    public function testConsumeTempTokenReturnsUserId(): void
    {
        $rawToken = str_repeat('a', 64);

        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn([
            'user_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $this->cache->method('getItem')->willReturn($item);
        $this->cache->expects($this->once())->method('deleteItem');

        $userId = $this->service->consumeTempToken($rawToken);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $userId);
    }

    public function testConsumeTempTokenReturnsNullWhenNotFound(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(false);

        $this->cache->method('getItem')->willReturn($item);
        $this->cache->expects($this->never())->method('deleteItem');

        $result = $this->service->consumeTempToken(str_repeat('b', 64));

        $this->assertNull($result);
    }

    public function testConsumeTempTokenInvalidatesAfterUse(): void
    {
        $rawToken = str_repeat('c', 64);

        // Первый вызов — токен найден
        $itemFound = $this->createMock(CacheItemInterface::class);
        $itemFound->method('isHit')->willReturn(true);
        $itemFound->method('get')->willReturn(['user_id' => 'some-id']);

        // Второй вызов — токен уже удалён (не найден)
        $itemMissing = $this->createMock(CacheItemInterface::class);
        $itemMissing->method('isHit')->willReturn(false);

        $this->cache->method('getItem')->willReturnOnConsecutiveCalls($itemFound, $itemMissing);
        $this->cache->expects($this->once())->method('deleteItem');

        $first = $this->service->consumeTempToken($rawToken);
        $second = $this->service->consumeTempToken($rawToken);

        $this->assertEquals('some-id', $first);
        $this->assertNull($second);
    }
}
