<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\DTO\Auth\LoginDTO;
use App\DTO\Auth\RegisterDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Vault\VaultService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenService $tokenService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly VaultService $vaultService,
        private readonly TotpService $totpService,
        private readonly TempTokenService $tempTokenService,
    ) {}

    public function register(RegisterDTO $dto): User
    {
        if ($this->userRepository->findByEmail($dto->email) !== null) {
            throw new \InvalidArgumentException('Email already in use');
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setSalt($dto->salt);
        $user->setKdfParams($dto->kdfParams);
        $user->setMasterPasswordHash($dto->masterPasswordHash);

        // Хэш пароля для Symfony Security (используем master password hash как пароль)
        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->masterPasswordHash);
        $user->setPasswordHash($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        // Создаём дефолтное хранилище
        $this->vaultService->createDefaultVault($user);

        return $user;
    }

    public function login(LoginDTO $dto, Request $request): array
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if ($user === null || !$user->isActive()) {
            throw new AuthenticationException('Invalid credentials');
        }

        // Верификация master password hash
        if (!$this->passwordHasher->isPasswordValid($user, $dto->masterPasswordHash)) {
            throw new AuthenticationException('Invalid credentials');
        }

        // Если 2FA включён — возвращаем temp_token вместо JWT
        if ($user->is2faEnabled()) {
            $tempToken = $this->tempTokenService->createTempToken($user);
            return [
                'requires_2fa' => true,
                'temp_token' => $tempToken,
            ];
        }

        return $this->buildTokenResponse($user, $request);
    }

    /**
     * Второй шаг логина с 2FA: верификация TOTP кода через temp_token.
     */
    public function loginWithTotp(string $tempToken, string $code, Request $request): array
    {
        // Читаем userId БЕЗ удаления токена — чтобы разрешить повторные попытки при неверном коде
        $userId = $this->tempTokenService->getUserIdByTempToken($tempToken);
        if ($userId === null) {
            throw new AuthenticationException('Invalid or expired 2FA session');
        }

        $user = $this->userRepository->find($userId);
        if ($user === null || !$user->isActive()) {
            throw new AuthenticationException('Invalid credentials');
        }

        // Проверяем TOTP код или backup-код
        if (!$this->totpService->verifyCode($user, $code) && !$this->totpService->verifyBackupCode($user, $code)) {
            // Токен НЕ удаляем — пользователь может попробовать снова (пока TTL не истёк)
            throw new AuthenticationException('Invalid 2FA code');
        }

        // Код верный — инвалидируем токен (защита от replay атак)
        $this->tempTokenService->invalidateTempToken($tempToken);

        // Если использовался backup-код — нужно сохранить изменения в entity
        $this->em->flush();

        return $this->buildTokenResponse($user, $request);
    }

    private function buildTokenResponse(User $user, Request $request): array
    {
        $tokenData = $this->refreshTokenService->createRefreshToken($user, $request);
        $accessToken = $this->tokenService->createAccessToken($user, $tokenData['sessionId']);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $tokenData['rawToken'],
            'token_type' => 'Bearer',
            'expires_in' => 900,
        ];
    }

    public function getKdfParams(string $email): array
    {
        $user = $this->userRepository->findByEmail($email);

        // Возвращаем дефолтные параметры даже для несуществующих email (защита от enumeration)
        if ($user === null) {
            return [
                'salt' => base64_encode(random_bytes(32)),
                'algorithm' => 'PBKDF2',
                'iterations' => 600000,
                'hash' => 'SHA-256',
                'keyLength' => 256,
            ];
        }

        return array_merge(
            ['salt' => $user->getSalt()],
            $user->getKdfParams(),
        );
    }
}
