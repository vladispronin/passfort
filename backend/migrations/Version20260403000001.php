<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add TOTP 2FA fields to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users
            ADD is_2fa_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ADD totp_secret VARCHAR(255) DEFAULT NULL,
            ADD backup_codes JSON DEFAULT NULL,
            ADD totp_setup_data JSON DEFAULT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users
            DROP is_2fa_enabled,
            DROP totp_secret,
            DROP backup_codes,
            DROP totp_setup_data
        ');
    }
}
