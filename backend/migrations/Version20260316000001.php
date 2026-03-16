<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, vaults, categories, vault_items, refresh_tokens, security_logs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id VARCHAR(36) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            master_password_hash VARCHAR(255) NOT NULL,
            salt VARCHAR(64) NOT NULL,
            kdf_params JSON NOT NULL,
            roles JSON NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            UNIQUE KEY UNIQ_email (email)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE vaults (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            KEY IDX_vault_user (user_id),
            CONSTRAINT FK_vault_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE categories (
            id VARCHAR(36) NOT NULL,
            vault_id VARCHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            color VARCHAR(7) DEFAULT NULL,
            icon VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            KEY IDX_category_vault (vault_id),
            CONSTRAINT FK_category_vault FOREIGN KEY (vault_id) REFERENCES vaults (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE vault_items (
            id VARCHAR(36) NOT NULL,
            vault_id VARCHAR(36) NOT NULL,
            category_id VARCHAR(36) DEFAULT NULL,
            encrypted_data LONGTEXT NOT NULL,
            iv VARCHAR(24) NOT NULL,
            auth_tag VARCHAR(24) NOT NULL,
            item_type VARCHAR(20) NOT NULL,
            title_hint VARCHAR(255) NOT NULL,
            is_favorite TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            KEY IDX_item_vault (vault_id),
            KEY IDX_item_category (category_id),
            CONSTRAINT FK_item_vault FOREIGN KEY (vault_id) REFERENCES vaults (id) ON DELETE CASCADE,
            CONSTRAINT FK_item_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE refresh_tokens (
            id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            device_info VARCHAR(255) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            UNIQUE KEY UNIQ_token_hash (token_hash),
            KEY IDX_refresh_token_user (user_id),
            CONSTRAINT FK_refresh_token_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE security_logs (
            id BIGINT AUTO_INCREMENT NOT NULL,
            user_id VARCHAR(36) DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(500) DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            KEY IDX_security_log_user (user_id),
            KEY IDX_security_log_action (action),
            CONSTRAINT FK_security_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE security_logs');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE vault_items');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE vaults');
        $this->addSql('DROP TABLE users');
    }
}
