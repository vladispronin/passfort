<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification: is_email_verified column and email_verification_tokens table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD is_email_verified TINYINT(1) NOT NULL DEFAULT 0');

        $this->addSql('
            CREATE TABLE email_verification_tokens (
                id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
                user_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                PRIMARY KEY(id),
                UNIQUE KEY UNIQ_evtoken_hash (token_hash),
                KEY IDX_evtoken_user (user_id),
                CONSTRAINT FK_evtoken_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_verification_tokens');
        $this->addSql('ALTER TABLE users DROP is_email_verified');
    }
}
