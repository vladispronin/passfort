<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate email verification token from separate table to columns in users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users
            ADD verification_token_hash VARCHAR(64) DEFAULT NULL,
            ADD verification_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        ");
        $this->addSql('DROP TABLE email_verification_tokens');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users
            DROP verification_token_hash,
            DROP verification_token_expires_at
        ');
        $this->addSql("CREATE TABLE email_verification_tokens (
            id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
            user_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            PRIMARY KEY(id),
            UNIQUE KEY UNIQ_evtoken_hash (token_hash),
            KEY IDX_evtoken_user (user_id),
            CONSTRAINT FK_evtoken_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }
}
