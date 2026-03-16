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

        $accessToken = $this->tokenService->createAccessToken($user);
        $refreshToken = $this->refreshTokenService->createRefreshToken($user, $request);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
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
