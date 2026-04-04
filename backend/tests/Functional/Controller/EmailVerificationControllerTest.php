<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\EmailVerificationToken;
use App\Tests\Functional\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

class EmailVerificationControllerTest extends ApiTestCase
{
    private string $masterPasswordHash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->masterPasswordHash = str_repeat('a', 64);
    }

    private function createVerificationToken(
        string $email,
        \DateTimeImmutable $expiresAt = null,
    ): string {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $userRepo = $em->getRepository(\App\Entity\User::class);
        $user = $userRepo->findOneBy(['email' => $email]);

        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        $token = new EmailVerificationToken();
        $token->setUser($user);
        $token->setTokenHash($hash);
        $token->setExpiresAt($expiresAt ?? new \DateTimeImmutable('+24 hours'));

        $em->persist($token);
        $em->flush();

        return $raw;
    }

    public function testVerifyEmailWithValidToken(): void
    {
        $this->createTestUser('verify@example.com', emailVerified: false);
        $rawToken = $this->createVerificationToken('verify@example.com');

        $response = $this->jsonRequest('GET', '/api/v1/auth/verify-email?token=' . $rawToken);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
        $this->assertStringContainsString('verified', strtolower($response['data']['message'] ?? ''));

        // Проверяем что пользователь действительно верифицирован в БД
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $userRepo = $em->getRepository(\App\Entity\User::class);
        $user = $userRepo->findOneBy(['email' => 'verify@example.com']);
        $this->assertTrue($user->isEmailVerified());
    }

    public function testVerifyEmailWithInvalidToken(): void
    {
        $response = $this->jsonRequest('GET', '/api/v1/auth/verify-email?token=' . str_repeat('a', 64));

        $this->assertEquals(400, $this->getStatusCode());
        $this->assertEquals('EMAIL_VERIFICATION_FAILED', $response['error_code'] ?? '');
    }

    public function testVerifyEmailWithExpiredToken(): void
    {
        $this->createTestUser('expired@example.com', emailVerified: false);
        $rawToken = $this->createVerificationToken(
            'expired@example.com',
            new \DateTimeImmutable('-1 hour'),
        );

        $response = $this->jsonRequest('GET', '/api/v1/auth/verify-email?token=' . $rawToken);

        $this->assertEquals(400, $this->getStatusCode());
        $this->assertEquals('EMAIL_VERIFICATION_FAILED', $response['error_code'] ?? '');
    }

    public function testVerifyEmailWithMissingToken(): void
    {
        $this->jsonRequest('GET', '/api/v1/auth/verify-email');

        $this->assertEquals(400, $this->getStatusCode());
    }

    public function testResendVerificationSuccess(): void
    {
        $this->createTestUser('resend@example.com', emailVerified: false);

        $response = $this->jsonRequest('POST', '/api/v1/auth/resend-verification', [
            'email' => 'resend@example.com',
        ]);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('data', $response);
    }

    public function testResendVerificationForNonExistentEmailReturns200(): void
    {
        // Не раскрываем факт существования email
        $response = $this->jsonRequest('POST', '/api/v1/auth/resend-verification', [
            'email' => 'nonexistent@example.com',
        ]);

        $this->assertEquals(200, $this->getStatusCode());
    }

    public function testResendVerificationForAlreadyVerifiedReturns200(): void
    {
        $this->createTestUser('verified@example.com', emailVerified: true);

        $response = $this->jsonRequest('POST', '/api/v1/auth/resend-verification', [
            'email' => 'verified@example.com',
        ]);

        $this->assertEquals(200, $this->getStatusCode());
    }

    public function testResendVerificationWithMissingEmailReturns400(): void
    {
        $this->jsonRequest('POST', '/api/v1/auth/resend-verification', []);

        $this->assertEquals(400, $this->getStatusCode());
    }

    public function testLoginBlockedForUnverifiedUser(): void
    {
        $this->createTestUser('unverified@example.com', emailVerified: false);

        $response = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'unverified@example.com',
            'masterPasswordHash' => $this->masterPasswordHash,
        ]);

        $this->assertEquals(403, $this->getStatusCode());
        $this->assertEquals('EMAIL_NOT_VERIFIED', $response['error_code'] ?? '');
    }

    public function testLoginSucceedsForVerifiedUser(): void
    {
        $this->createTestUser('verified_login@example.com', emailVerified: true);

        $response = $this->jsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'verified_login@example.com',
            'masterPasswordHash' => $this->masterPasswordHash,
        ]);

        $this->assertEquals(200, $this->getStatusCode());
        $this->assertArrayHasKey('access_token', $response['data'] ?? []);
    }
}
