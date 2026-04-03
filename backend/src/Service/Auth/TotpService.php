<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use OTPHP\TOTP;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class TotpService
{
    private const BACKUP_CODES_COUNT = 10;
    private const BACKUP_CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const BACKUP_CODE_LENGTH = 8;
    private const TOTP_WINDOW = 1;

    public function __construct(
        #[Autowire(env: 'APP_SECRET')]
        private readonly string $appSecret,
    ) {}

    /**
     * Генерирует данные для настройки TOTP (не активирует 2FA).
     * Сохраняет зашифрованный секрет в totpSetupData для последующей верификации.
     */
    public function generateSetupData(User $user): array
    {
        $totp = TOTP::generate();
        $totp->setIssuer('PassFort');
        $totp->setLabel($user->getEmail());

        $rawSecret = $totp->getSecret();
        $encryptedSecret = $this->encryptSecret($rawSecret);

        $user->setTotpSetupData([
            'encrypted_secret' => $encryptedSecret,
            'created_at' => time(),
        ]);

        return [
            'secret' => $rawSecret,
            'qr_uri' => $totp->getProvisioningUri(),
        ];
    }

    /**
     * Верифицирует код и активирует 2FA.
     * Возвращает массив raw backup-кодов при успехе, false при неверном коде.
     */
    public function verifyAndEnable(User $user, string $code): array|false
    {
        $setupData = $user->getTotpSetupData();
        if ($setupData === null || !isset($setupData['encrypted_secret'])) {
            return false;
        }

        $rawSecret = $this->decryptSecret($setupData['encrypted_secret']);
        $totp = TOTP::createFromSecret($rawSecret);
        $totp->setIssuer('PassFort');
        $totp->setLabel($user->getEmail());

        if (!$totp->verify($code, null, self::TOTP_WINDOW)) {
            return false;
        }

        ['raw' => $rawCodes, 'hashed' => $hashedCodes] = $this->generateBackupCodes();

        $user->setTotpSecret($this->encryptSecret($rawSecret));
        $user->setIs2faEnabled(true);
        $user->setBackupCodes($hashedCodes);
        $user->setTotpSetupData(null);

        return $rawCodes;
    }

    /**
     * Верифицирует TOTP код для уже включённого 2FA.
     */
    public function verifyCode(User $user, string $code): bool
    {
        if (!$user->is2faEnabled() || $user->getTotpSecret() === null) {
            return false;
        }

        $rawSecret = $this->decryptSecret($user->getTotpSecret());
        $totp = TOTP::createFromSecret($rawSecret);
        $totp->setIssuer('PassFort');
        $totp->setLabel($user->getEmail());

        return $totp->verify($code, null, self::TOTP_WINDOW);
    }

    /**
     * Верифицирует backup-код (одноразовый). При успехе удаляет код из списка.
     */
    public function verifyBackupCode(User $user, string $code): bool
    {
        $backupCodes = $user->getBackupCodes();
        if (empty($backupCodes)) {
            return false;
        }

        $hashedCode = hash('sha256', strtoupper(trim($code)));
        $index = array_search($hashedCode, $backupCodes, true);

        if ($index === false) {
            return false;
        }

        // Удаляем использованный код
        array_splice($backupCodes, (int) $index, 1);
        $user->setBackupCodes(array_values($backupCodes));

        return true;
    }

    /**
     * Генерирует новые backup-коды, заменяя старые.
     * Возвращает raw коды для отображения пользователю.
     */
    public function regenerateBackupCodes(User $user): array
    {
        ['raw' => $rawCodes, 'hashed' => $hashedCodes] = $this->generateBackupCodes();
        $user->setBackupCodes($hashedCodes);
        return $rawCodes;
    }

    /**
     * Отключает 2FA, очищает все данные TOTP.
     */
    public function disable(User $user): void
    {
        $user->setIs2faEnabled(false);
        $user->setTotpSecret(null);
        $user->setBackupCodes(null);
        $user->setTotpSetupData(null);
    }

    private function generateBackupCodes(): array
    {
        $raw = [];
        $hashed = [];
        $chars = self::BACKUP_CODE_CHARS;
        $len = strlen($chars);

        for ($i = 0; $i < self::BACKUP_CODES_COUNT; $i++) {
            $code = '';
            for ($j = 0; $j < self::BACKUP_CODE_LENGTH; $j++) {
                $code .= $chars[random_int(0, $len - 1)];
            }
            $raw[] = $code;
            $hashed[] = hash('sha256', $code);
        }

        return ['raw' => $raw, 'hashed' => $hashed];
    }

    private function encryptSecret(string $secret): string
    {
        $key = substr(hash('sha256', $this->appSecret, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($secret, $nonce, $key);
        return base64_encode($nonce . $ciphertext);
    }

    private function decryptSecret(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid encrypted secret format');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = substr(hash('sha256', $this->appSecret, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt TOTP secret');
        }

        return $plaintext;
    }
}
