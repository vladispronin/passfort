<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\DTO\User\ChangeMasterPasswordDTO;
use App\DTO\User\ReEncryptedItemDTO;
use App\Entity\User;
use App\Entity\VaultItem;
use App\Repository\VaultItemRepository;
use App\Service\Auth\RefreshTokenService;
use App\Service\Auth\TokenService;
use App\Service\Security\SecurityLogService;
use App\Service\User\MasterPasswordService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Uid\Uuid;

class MasterPasswordServiceTest extends TestCase
{
    private MasterPasswordService $service;
    private EntityManagerInterface&MockObject $em;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private VaultItemRepository&MockObject $vaultItemRepository;
    private RefreshTokenService&MockObject $refreshTokenService;
    private TokenService&MockObject $tokenService;
    private SecurityLogService&MockObject $securityLogService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->vaultItemRepository = $this->createMock(VaultItemRepository::class);
        $this->refreshTokenService = $this->createMock(RefreshTokenService::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->securityLogService = $this->createMock(SecurityLogService::class);

        $this->service = new MasterPasswordService(
            $this->em,
            $this->passwordHasher,
            $this->vaultItemRepository,
            $this->refreshTokenService,
            $this->tokenService,
            $this->securityLogService,
        );
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setSalt(str_repeat('a', 32));
        $user->setKdfParams(['algorithm' => 'PBKDF2']);
        $user->setMasterPasswordHash(str_repeat('a', 64));
        $user->setPasswordHash('hashed');
        return $user;
    }

    private function makeDto(array $items = []): ChangeMasterPasswordDTO
    {
        $dto = new ChangeMasterPasswordDTO();
        $dto->currentMasterPasswordHash = str_repeat('a', 64);
        $dto->newMasterPasswordHash = str_repeat('b', 64);
        $dto->newSalt = str_repeat('c', 44);
        $dto->newKdfParams = ['algorithm' => 'PBKDF2', 'iterations' => 600000];
        $dto->items = $items;
        return $dto;
    }

    private function makeVaultItem(string $uuid): VaultItem
    {
        $item = $this->createMock(VaultItem::class);
        $uuidObj = Uuid::fromString($uuid);
        $item->method('getId')->willReturn($uuidObj);
        return $item;
    }

    public function testChangeMasterPasswordSuccess(): void
    {
        $user = $this->makeUser();
        $uuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $vaultItem = $this->makeVaultItem($uuid);

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(true);

        $this->vaultItemRepository->expects($this->once())
            ->method('findAllByUser')
            ->willReturn([$vaultItem]);

        // Мок для setEncryptedData/setIv/setAuthTag
        $vaultItem->expects($this->once())->method('setEncryptedData');
        $vaultItem->expects($this->once())->method('setIv');
        $vaultItem->expects($this->once())->method('setAuthTag');

        $this->em->expects($this->once())->method('beginTransaction');
        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('new_hashed');
        $this->em->expects($this->once())->method('flush');
        $this->em->expects($this->once())->method('commit');

        $this->refreshTokenService->expects($this->once())->method('revokeAllUserTokens');
        $this->tokenService->expects($this->once())
            ->method('createAccessToken')
            ->willReturn('access_token_value');
        $this->refreshTokenService->expects($this->once())
            ->method('createRefreshToken')
            ->willReturn('refresh_token_value');

        $itemDto = new ReEncryptedItemDTO();
        $itemDto->id = $uuid;
        $itemDto->encryptedData = 'newEncrypted';
        $itemDto->iv = str_repeat('x', 16);
        $itemDto->authTag = str_repeat('y', 16);

        $result = $this->service->changeMasterPassword($user, $this->makeDto([$itemDto]), new Request());

        $this->assertEquals('access_token_value', $result['access_token']);
        $this->assertEquals('refresh_token_value', $result['refresh_token']);
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertEquals(900, $result['expires_in']);
    }

    public function testChangeMasterPasswordInvalidCurrentPassword(): void
    {
        $user = $this->makeUser();

        $this->passwordHasher->method('isPasswordValid')->willReturn(false);

        $this->expectException(AuthenticationException::class);

        $this->service->changeMasterPassword($user, $this->makeDto(), new Request());
    }

    public function testChangeMasterPasswordWithForeignItems(): void
    {
        $user = $this->makeUser();
        $ownUuid = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
        $foreignUuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $vaultItem = $this->makeVaultItem($ownUuid);

        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->vaultItemRepository->method('findAllByUser')->willReturn([$vaultItem]);

        $itemDto = new ReEncryptedItemDTO();
        $itemDto->id = $foreignUuid;
        $itemDto->encryptedData = 'enc';
        $itemDto->iv = str_repeat('x', 16);
        $itemDto->authTag = str_repeat('y', 16);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Items do not belong to current user');

        $this->service->changeMasterPassword($user, $this->makeDto([$itemDto]), new Request());
    }

    public function testChangeMasterPasswordEmptyItemsAllowed(): void
    {
        $user = $this->makeUser();

        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->vaultItemRepository->method('findAllByUser')->willReturn([]);

        $this->em->expects($this->once())->method('beginTransaction');
        $this->em->expects($this->once())->method('flush');
        $this->em->expects($this->once())->method('commit');

        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->tokenService->method('createAccessToken')->willReturn('token');
        $this->refreshTokenService->method('createRefreshToken')->willReturn('refresh');

        $result = $this->service->changeMasterPassword($user, $this->makeDto([]), new Request());

        $this->assertArrayHasKey('access_token', $result);
    }

    public function testChangeMasterPasswordRollsBackOnFlushError(): void
    {
        $user = $this->makeUser();

        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->vaultItemRepository->method('findAllByUser')->willReturn([]);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');

        $this->em->expects($this->once())->method('beginTransaction');
        $this->em->expects($this->once())->method('flush')
            ->willThrowException(new \RuntimeException('DB error'));
        $this->em->expects($this->once())->method('rollback');
        $this->em->expects($this->never())->method('commit');

        $this->expectException(\RuntimeException::class);

        $this->service->changeMasterPassword($user, $this->makeDto([]), new Request());
    }

    public function testChangeMasterPasswordRevokesAllTokens(): void
    {
        $user = $this->makeUser();

        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->vaultItemRepository->method('findAllByUser')->willReturn([]);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->em->method('flush');
        $this->em->method('commit');
        $this->tokenService->method('createAccessToken')->willReturn('token');
        $this->refreshTokenService->method('createRefreshToken')->willReturn('refresh');

        $this->refreshTokenService->expects($this->once())
            ->method('revokeAllUserTokens')
            ->with($user);

        $this->service->changeMasterPassword($user, $this->makeDto([]), new Request());
    }

    public function testChangeMasterPasswordLogsSecurityEvent(): void
    {
        $user = $this->makeUser();

        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->vaultItemRepository->method('findAllByUser')->willReturn([]);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->em->method('flush');
        $this->em->method('commit');
        $this->tokenService->method('createAccessToken')->willReturn('token');
        $this->refreshTokenService->method('createRefreshToken')->willReturn('refresh');

        $this->securityLogService->expects($this->once())
            ->method('log')
            ->with('user.master_password_changed', $user);

        $this->service->changeMasterPassword($user, $this->makeDto([]), new Request());
    }
}
