<?php

declare(strict_types=1);

namespace App\Service\User;

use App\DTO\User\ChangeMasterPasswordDTO;
use App\DTO\User\ReEncryptedItemDTO;
use App\Entity\User;
use App\Entity\VaultItem;
use App\Repository\VaultItemRepository;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TokenService;
use App\Service\Security\SecurityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class MasterPasswordService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly VaultItemRepository $vaultItemRepository,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly TokenService $tokenService,
        private readonly SecurityLogService $securityLogService,
    ) {}

    /**
     * Меняет мастер-пароль: перешифровывает все vault items, обновляет соль и KDF-параметры.
     * Инвалидирует все сессии и выдаёт новые токены.
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function changeMasterPassword(User $user, ChangeMasterPasswordDTO $dto, Request $request): array
    {
        // 1. Верификация текущего пароля
        if (!$this->passwordHasher->isPasswordValid($user, $dto->currentMasterPasswordHash)) {
            throw new AuthenticationException('Invalid current master password');
        }

        // 2. Проверка ownership всех переданных items
        $userItems = $this->vaultItemRepository->findAllByUser($user);
        $userItemIds = array_map(
            static fn(VaultItem $vi) => $vi->getId()->toRfc4122(),
            $userItems,
        );
        $requestedIds = array_map(
            static fn(ReEncryptedItemDTO $i) => $i->id,
            $dto->items,
        );

        $foreignIds = array_diff($requestedIds, $userItemIds);
        if (!empty($foreignIds)) {
            throw new \RuntimeException('Items do not belong to current user');
        }

        // Индексируем по UUID для O(1) доступа при обновлении
        $itemsMap = [];
        foreach ($userItems as $item) {
            $itemsMap[$item->getId()->toRfc4122()] = $item;
        }

        // 3. Атомарное обновление пользователя и всех vault items
        $this->em->beginTransaction();
        try {
            $user->setSalt($dto->newSalt);
            $user->setKdfParams($dto->newKdfParams);
            $user->setMasterPasswordHash($dto->newMasterPasswordHash);
            $user->setPasswordHash(
                $this->passwordHasher->hashPassword($user, $dto->newMasterPasswordHash),
            );

            foreach ($dto->items as $itemDto) {
                $item = $itemsMap[$itemDto->id];
                $item->setEncryptedData($itemDto->encryptedData);
                $item->setIv($itemDto->iv);
                $item->setAuthTag($itemDto->authTag);
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        // 4. Инвалидация всех активных сессий
        $this->refreshTokenService->revokeAllUserTokens($user);

        // 5. Логирование события безопасности
        $this->securityLogService->log(
            'user.master_password_changed',
            $user,
            $request,
            ['items_count' => count($dto->items)],
        );

        // 6. Выдача новых токенов
        $tokenData = $this->refreshTokenService->createRefreshToken($user, $request);
        return [
            'access_token' => $this->tokenService->createAccessToken($user, $tokenData['sessionId']),
            'refresh_token' => $tokenData['rawToken'],
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ];
    }
}
