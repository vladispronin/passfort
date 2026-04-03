<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Repository\RefreshTokenRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded')]
class SessionValidationListener
{
    private const CACHE_TTL = 60; // секунд
    private const CACHE_PREFIX = 'session_valid_';

    public function __construct(
        private readonly RefreshTokenRepository $repository,
        private readonly CacheItemPoolInterface $cache,
    ) {}

    public function __invoke(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        if (!isset($payload['session_id'])) {
            // JWT без session_id — старый токен, пропускаем для обратной совместимости
            return;
        }

        $sessionId = $payload['session_id'];
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $sessionId);

        // Проверяем кэш
        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            return;
        }

        // Проверяем БД
        try {
            $uuid = Uuid::fromString($sessionId);
        } catch (\InvalidArgumentException) {
            $event->markAsInvalid();
            return;
        }

        $token = $this->repository->find($uuid);
        if ($token === null || $token->isExpired()) {
            $event->markAsInvalid();
            return;
        }

        // Кэшируем на 60 секунд
        $item->set(true);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);
    }
}
