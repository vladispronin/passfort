<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use Psr\Cache\CacheItemPoolInterface;

class TempTokenService
{
    private const TTL = 300; // 5 минут
    private const PREFIX = 'totp_pending_';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {}

    /**
     * Создаёт временный токен для 2FA верификации.
     * Возвращает raw токен (не хэшированный).
     */
    public function createTempToken(User $user): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $key = $this->buildKey($rawToken);

        $item = $this->cache->getItem($key);
        $item->set([
            'user_id' => $user->getId()?->toRfc4122(),
        ]);
        $item->expiresAfter(self::TTL);
        $this->cache->save($item);

        return $rawToken;
    }

    /**
     * Читает userId из токена БЕЗ его инвалидации.
     * Используется для проверки кода при 2FA — удаление только при успехе.
     */
    public function getUserIdByTempToken(string $rawToken): ?string
    {
        $key = $this->buildKey($rawToken);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();
        if (!is_array($data) || !isset($data['user_id'])) {
            return null;
        }

        return $data['user_id'];
    }

    /**
     * Инвалидирует токен после успешного использования (защита от replay атак).
     */
    public function invalidateTempToken(string $rawToken): void
    {
        $this->cache->deleteItem($this->buildKey($rawToken));
    }

    /**
     * Потребляет temp_token — возвращает userId и инвалидирует токен.
     * Повторный вызов вернёт null.
     *
     * @deprecated Используй getUserIdByTempToken() + invalidateTempToken() раздельно
     */
    public function consumeTempToken(string $rawToken): ?string
    {
        $userId = $this->getUserIdByTempToken($rawToken);
        if ($userId !== null) {
            $this->invalidateTempToken($rawToken);
        }
        return $userId;
    }

    private function buildKey(string $rawToken): string
    {
        // Используем хэш токена как ключ (не храним raw токен в Redis)
        return self::PREFIX . hash('sha256', $rawToken);
    }
}
