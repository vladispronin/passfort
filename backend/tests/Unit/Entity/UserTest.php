<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $user = new User();

        // Проверяем значения по умолчанию
        $this->assertTrue($user->isActive());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
        $this->assertCount(0, $user->getVaults());
        $this->assertNull($user->getId());
    }

    public function testSetAndGetEmail(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('test@example.com', $user->getUserIdentifier());
    }

    public function testSetAndGetSalt(): void
    {
        $user = new User();
        $user->setSalt('abc123salt');

        $this->assertEquals('abc123salt', $user->getSalt());
    }

    public function testSetAndGetKdfParams(): void
    {
        $user = new User();
        $params = ['algorithm' => 'argon2id', 'iterations' => 3];
        $user->setKdfParams($params);

        $this->assertEquals($params, $user->getKdfParams());
    }

    public function testSetAndGetPasswordHash(): void
    {
        $user = new User();
        $user->setPasswordHash('hashed_password');

        $this->assertEquals('hashed_password', $user->getPasswordHash());
        // getPassword должен возвращать passwordHash (для Symfony Security)
        $this->assertEquals('hashed_password', $user->getPassword());
    }

    public function testSetAndGetMasterPasswordHash(): void
    {
        $user = new User();
        $user->setMasterPasswordHash(str_repeat('a', 64));

        $this->assertEquals(str_repeat('a', 64), $user->getMasterPasswordHash());
    }

    public function testSetIsActive(): void
    {
        $user = new User();
        $user->setIsActive(false);

        $this->assertFalse($user->isActive());
    }

    public function testSetRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
    }

    public function testEraseCredentials(): void
    {
        // eraseCredentials не должен бросать исключений
        $user = new User();
        $user->eraseCredentials();
        $this->assertTrue(true); // если дошли сюда — всё ок
    }
}
