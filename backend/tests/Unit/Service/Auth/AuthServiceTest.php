<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\DTO\Auth\LoginDTO;
use App\DTO\Auth\RegisterDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Auth\AuthService;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TokenService;
use App\Service\Vault\VaultService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AuthServiceTest extends TestCase
{
    private AuthService $service;
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $em;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private TokenService&MockObject $tokenService;
    private RefreshTokenService&MockObject $refreshTokenService;
    private VaultService&MockObject $vaultService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->refreshTokenService = $this->createMock(RefreshTokenService::class);
        $this->vaultService = $this->createMock(VaultService::class);

        $this->service = new AuthService(
            $this->userRepository,
            $this->em,
            $this->passwordHasher,
            $this->tokenService,
            $this->refreshTokenService,
            $this->vaultService,
        );
    }

    private function makeRegisterDto(): RegisterDTO
    {
        $dto = new RegisterDTO();
        $dto->email = 'new@example.com';
        $dto->masterPasswordHash = str_repeat('a', 64);
        $dto->salt = str_repeat('b', 32);
        $dto->kdfParams = ['algorithm' => 'argon2id', 'iterations' => 3];
        return $dto;
    }

    public function testRegisterSuccess(): void
    {
        $dto = $this->makeRegisterDto();

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('new@example.com')
            ->willReturn(null);

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->vaultService->expects($this->once())->method('createDefaultVault');

        $user = $this->service->register($dto);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('new@example.com', $user->getEmail());
        $this->assertEquals($dto->salt, $user->getSalt());
        $this->assertEquals($dto->kdfParams, $user->getKdfParams());
        $this->assertEquals($dto->masterPasswordHash, $user->getMasterPasswordHash());
    }

    public function testRegisterThrowsWhenEmailTaken(): void
    {
        $dto = $this->makeRegisterDto();

        $existingUser = new User();
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($existingUser);

        $this->em->expects($this->never())->method('persist');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email already in use');

        $this->service->register($dto);
    }

    public function testLoginSuccess(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(true);

        $dto = new LoginDTO();
        $dto->email = 'test@example.com';
        $dto->masterPasswordHash = str_repeat('a', 64);

        $request = Request::create('/');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($user);

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $dto->masterPasswordHash)
            ->willReturn(true);

        $this->tokenService->expects($this->once())
            ->method('createAccessToken')
            ->with($user)
            ->willReturn('access_token_value');

        $this->refreshTokenService->expects($this->once())
            ->method('createRefreshToken')
            ->with($user, $request)
            ->willReturn('refresh_token_value');

        $result = $this->service->login($dto, $request);

        $this->assertEquals('access_token_value', $result['access_token']);
        $this->assertEquals('refresh_token_value', $result['refresh_token']);
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertEquals(900, $result['expires_in']);
    }

    public function testLoginFailsUserNotFound(): void
    {
        $dto = new LoginDTO();
        $dto->email = 'nonexistent@example.com';
        $dto->masterPasswordHash = str_repeat('a', 64);

        $request = Request::create('/');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->expectException(AuthenticationException::class);

        $this->service->login($dto, $request);
    }

    public function testLoginFailsUserInactive(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(false);

        $dto = new LoginDTO();
        $dto->email = 'test@example.com';
        $dto->masterPasswordHash = str_repeat('a', 64);

        $request = Request::create('/');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($user);

        $this->expectException(AuthenticationException::class);

        $this->service->login($dto, $request);
    }

    public function testLoginFailsWrongPassword(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(true);

        $dto = new LoginDTO();
        $dto->email = 'test@example.com';
        $dto->masterPasswordHash = str_repeat('a', 64);

        $request = Request::create('/');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($user);

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(false);

        $this->expectException(AuthenticationException::class);

        $this->service->login($dto, $request);
    }

    public function testGetKdfParamsForExistingUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setSalt('salt_value');
        $user->setKdfParams(['algorithm' => 'argon2id', 'iterations' => 3]);

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $result = $this->service->getKdfParams('test@example.com');

        $this->assertEquals('salt_value', $result['salt']);
        $this->assertEquals('argon2id', $result['algorithm']);
    }

    public function testGetKdfParamsForNonExistentUser(): void
    {
        // Для несуществующих email возвращаем дефолтные параметры (защита от enumeration)
        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);

        $result = $this->service->getKdfParams('nonexistent@example.com');

        // Должны вернуться дефолтные параметры с рандомной солью
        $this->assertArrayHasKey('salt', $result);
        $this->assertArrayHasKey('algorithm', $result);
        $this->assertArrayHasKey('iterations', $result);
        $this->assertEquals('PBKDF2', $result['algorithm']);
    }
}
