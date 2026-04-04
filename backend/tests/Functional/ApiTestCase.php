<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Базовый класс для функциональных тестов API.
 * После каждого теста очищает тестовые данные через TRUNCATE.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    /** Список созданных тестовых пользователей для очистки после теста */
    private array $createdUserEmails = [];

    protected function setUp(): void
    {
        // createClient() должен вызываться до getContainer()
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->createdUserEmails = [];
    }

    protected function tearDown(): void
    {
        // Очищаем тестовые данные в правильном порядке (учитывая FK)
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Удаляет все тестовые данные через TRUNCATE с отключёнными FK-проверками.
     */
    private function cleanupTestData(): void
    {
        try {
            $conn = $this->em->getConnection();
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
            $conn->executeStatement('DELETE FROM security_logs');
            $conn->executeStatement('DELETE FROM refresh_tokens');
            $conn->executeStatement('DELETE FROM vault_items');
            $conn->executeStatement('DELETE FROM categories');
            $conn->executeStatement('DELETE FROM vaults');
            $conn->executeStatement('DELETE FROM users');
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Throwable) {
            // Не должно ломать тест, если БД недоступна
        }
    }

    /**
     * Создаёт тестового пользователя и сохраняет его в базу данных.
     */
    protected function createTestUser(
        string $email = 'test@example.com',
        ?string $masterPasswordHash = null,
        bool $emailVerified = true,
    ): User {
        $masterPasswordHash = $masterPasswordHash ?? str_repeat('a', 64);

        // Создаём пользователя через новый EM чтобы данные были видны KernelBrowser
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setSalt(bin2hex(random_bytes(16)));
        $user->setKdfParams(['algorithm' => 'argon2id', 'iterations' => 3]);
        $user->setMasterPasswordHash($masterPasswordHash);
        $user->setIsEmailVerified($emailVerified);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $hashed = $hasher->hashPassword($user, $masterPasswordHash);
        $user->setPasswordHash($hashed);

        $em->persist($user);
        $em->flush();

        $this->createdUserEmails[] = $email;

        return $user;
    }

    /**
     * Генерирует JWT-токен для пользователя.
     */
    protected function getJwtToken(User $user): string
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        return $jwtManager->create($user);
    }

    /**
     * Выполняет JSON-запрос к API и возвращает декодированный ответ.
     */
    protected function jsonRequest(string $method, string $uri, array $data = [], ?string $token = null): array
    {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $headers,
            $data !== [] ? json_encode($data, JSON_THROW_ON_ERROR) : null
        );

        return $this->getJsonResponse();
    }

    /**
     * Возвращает декодированный JSON из последнего ответа.
     */
    protected function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        return json_decode($content ?: '{}', true) ?? [];
    }

    /**
     * Возвращает HTTP-статус код последнего ответа.
     */
    protected function getStatusCode(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }
}
