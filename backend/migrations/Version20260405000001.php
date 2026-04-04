<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260405000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email change token columns to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users
            ADD pending_email VARCHAR(255) DEFAULT NULL,
            ADD email_change_token_hash VARCHAR(64) DEFAULT NULL,
            ADD email_change_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users
            DROP pending_email,
            DROP email_change_token_hash,
            DROP email_change_token_expires_at
        ');
    }
}
