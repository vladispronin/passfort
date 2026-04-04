<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Auth;

use App\DTO\Auth\LoginDTO;
use App\DTO\Auth\RegisterDTO;
use App\Entity\User;
use App\Exception\EmailNotVerifiedException;
use App\Repository\UserRepository;
use App\Service\Auth\AuthService;
use App\Service\Auth\EmailVerificationService;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TempTokenService;
use App\Service\Auth\TokenService;
use App\Service\Auth\TotpService;
use App\Service\Vault\VaultService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Uid\Uuid;

class AuthServiceTest extends TestCase
{
    private AuthService $service;
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $em;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private TokenService&MockObject $tokenService;
    private RefreshTokenService&MockObject $refreshTokenService;
    private VaultService&MockObject $vaultService;
    private TotpService&MockObject $totpService;
    private TempTokenService&MockObject $tempTokenService;
    private EmailVerificationService&MockObject $emailVerificationService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->refreshTokenService = $this->createMock(RefreshTokenService::class);
        $this->vaultService = $this->createMock(VaultService::class);
        $this->totpService = $this->createMock(TotpService::class);
        $this->tempTokenService = $this->createMock(TempTokenService::class);
        $this->emailVerificationService = $this->createMock(EmailVerificationService::class);

        $this->service = new AuthService(
            $this->userRepository,
            $this->em,
            $this->passwordHasher,
            $this->tokenService,
            $this->refreshTokenService,
            $this->vaultService,
            $this->totpService,
            $this->tempTokenService,
            $this->emailVerificationService,
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

        $this->emailVerificationService->expects($this->once())->method('sendVerificationEmail');

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
        $user->setIsEmailVerified(true);

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
            ->willReturn('access_token_value');

        $this->refreshTokenService->expects($this->once())
            ->method('createRefreshToken')
            ->with($user, $request)
            ->willReturn(['rawToken' => 'refresh_token_value', 'sessionId' => 'test-session-uuid']);

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
        $user->setIsEmailVerified(true);

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

    public function testLoginReturnsTempTokenWhen2faEnabled(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(true);
        $user->setIsEmailVerified(true);
        $user->setIs2faEnabled(true);

        $dto = new LoginDTO();
        $dto->email = 'test@example.com';
        $dto->masterPasswordHash = str_repeat('a', 64);

        $request = Request::create('/');

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->tempTokenService->expects($this->once())
            ->method('createTempToken')
            ->with($user)
            ->willReturn('temp_token_value');

        $result = $this->service->login($dto, $request);

        $this->assertTrue($result['requires_2fa']);
        $this->assertEquals('temp_token_value', $result['temp_token']);
        $this->assertArrayNotHasKey('access_token', $result);
    }

    public function testLoginReturnsFullTokensWhen2faDisabled(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(true);
        $user->setIsEmailVerified(true);
        // is2faEnabled = false по умолчанию

        $dto = new LoginDTO();
        $dto->email = 'test@example.com';
        $dto->masterPasswordHash = str_repeat('a', 64);

        $request = Request::create('/');

        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->tokenService->method('createAccessToken')->willReturn('access_token');
        $this->refreshTokenService->method('createRefreshToken')->willReturn(['rawToken' => 'refresh_token', 'sessionId' => 'test-session-uuid']);
        $this->tempTokenService->expects($this->never())->method('createTempToken');

        $result = $this->service->login($dto, $request);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayNotHasKey('requires_2fa', $result);
    }

    public function testLoginWithTotpSuccessWithValidCode(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(true);

        $request = Request::create('/');

        $this->tempTokenService->expects($this->once())
            ->method('getUserIdByTempToken')
            ->with('raw_temp_token')
            ->willReturn($userId);

        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->willReturn($user);

        $this->totpService->expects($this->once())
            ->method('verifyCode')
            ->with($user, '123456')
            ->willReturn(true);

        // При успехе токен должен инвалидироваться
        $this->tempTokenService->expects($this->once())
            ->method('invalidateTempToken')
            ->with('raw_temp_token');

        $this->tokenService->method('createAccessToken')->willReturn('access_token');
        $this->refreshTokenService->method('createRefreshToken')->willReturn(['rawToken' => 'refresh_token', 'sessionId' => 'test-session-uuid']);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->loginWithTotp('raw_temp_token', '123456', $request);

        $this->assertEquals('access_token', $result['access_token']);
        $this->assertEquals('Bearer', $result['token_type']);
    }

    public function testLoginWithTotpSuccessWithBackupCode(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(true);

        $request = Request::create('/');

        $this->tempTokenService->method('getUserIdByTempToken')->willReturn($userId);
        $this->userRepository->method('find')->willReturn($user);

        $this->totpService->method('verifyCode')->willReturn(false);
        $this->totpService->expects($this->once())
            ->method('verifyBackupCode')
            ->with($user, 'BACKUP12')
            ->willReturn(true);

        $this->tempTokenService->expects($this->once())->method('invalidateTempToken');
        $this->tokenService->method('createAccessToken')->willReturn('access_token');
        $this->refreshTokenService->method('createRefreshToken')->willReturn(['rawToken' => 'refresh_token', 'sessionId' => 'test-session-uuid']);
        $this->em->method('flush');

        $result = $this->service->loginWithTotp('raw_temp_token', 'BACKUP12', $request);

        $this->assertArrayHasKey('access_token', $result);
    }

    public function testLoginWithTotpFailsWithExpiredTempToken(): void
    {
        $request = Request::create('/');

        $this->tempTokenService->method('getUserIdByTempToken')->willReturn(null);
        // Токен не должен инвалидироваться при отсутствии сессии
        $this->tempTokenService->expects($this->never())->method('invalidateTempToken');

        $this->expectException(AuthenticationException::class);

        $this->service->loginWithTotp('invalid_token', '123456', $request);
    }

    public function testLoginFailsEmailNotVerified(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(true);
        // isEmailVerified = false по умолчанию

        $dto = new LoginDTO();
        $dto->email = 'test@example.com';
        $dto->masterPasswordHash = str_repeat('a', 64);

        $request = Request::create('/');

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->willReturn($user);

        // Пароль НЕ должен проверяться — блокировка раньше
        $this->passwordHasher->expects($this->never())->method('isPasswordValid');

        $this->expectException(EmailNotVerifiedException::class);

        $this->service->login($dto, $request);
    }

    public function testLoginWithTotpFailsWithInvalidCode(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setIsActive(true);

        $request = Request::create('/');

        $this->tempTokenService->method('getUserIdByTempToken')->willReturn($userId);
        $this->userRepository->method('find')->willReturn($user);
        $this->totpService->method('verifyCode')->willReturn(false);
        $this->totpService->method('verifyBackupCode')->willReturn(false);

        // При неверном коде токен НЕ должен инвалидироваться — пользователь может попробовать снова
        $this->tempTokenService->expects($this->never())->method('invalidateTempToken');

        $this->expectException(AuthenticationException::class);

        $this->service->loginWithTotp('raw_temp_token', '000000', $request);
    }
}
