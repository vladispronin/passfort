<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace action VARCHAR(100) with TINYINT UNSIGNED (SecurityLogAction enum)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_logs ADD COLUMN action_code TINYINT UNSIGNED NOT NULL DEFAULT 0');

        $this->addSql("
            UPDATE security_logs SET action_code = CASE action
                WHEN 'user.register'                  THEN 1
                WHEN 'user.login'                     THEN 2
                WHEN 'user.login.failed'              THEN 3
                WHEN 'user.login.email_not_verified'  THEN 4
                WHEN 'user.login.2fa_required'        THEN 5
                WHEN 'user.login.2fa_success'         THEN 6
                WHEN 'user.login.2fa_failed'          THEN 7
                WHEN 'user.email_verified'            THEN 8
                WHEN 'user.resend_verification'       THEN 9
                WHEN 'user.email_change_requested'    THEN 10
                WHEN 'user.email_changed'             THEN 11
                WHEN 'user.master_password_changed'   THEN 12
                WHEN 'user.logout'                    THEN 13
                WHEN 'session.revoked'                THEN 14
                WHEN '2fa.setup.initiated'            THEN 15
                WHEN '2fa.enabled'                    THEN 16
                WHEN '2fa.disabled'                   THEN 17
                WHEN '2fa.backup_codes_regenerated'   THEN 18
                ELSE 0
            END
        ");

        $this->addSql('DROP INDEX idx_security_logs_action ON security_logs');
        $this->addSql('ALTER TABLE security_logs DROP COLUMN action');
        $this->addSql('ALTER TABLE security_logs CHANGE action_code action TINYINT UNSIGNED NOT NULL');
        $this->addSql('CREATE INDEX idx_security_logs_action ON security_logs (action)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_logs ADD COLUMN action_old VARCHAR(100) NOT NULL DEFAULT \'\'');

        $this->addSql("
            UPDATE security_logs SET action_old = CASE action
                WHEN 1  THEN 'user.register'
                WHEN 2  THEN 'user.login'
                WHEN 3  THEN 'user.login.failed'
                WHEN 4  THEN 'user.login.email_not_verified'
                WHEN 5  THEN 'user.login.2fa_required'
                WHEN 6  THEN 'user.login.2fa_success'
                WHEN 7  THEN 'user.login.2fa_failed'
                WHEN 8  THEN 'user.email_verified'
                WHEN 9  THEN 'user.resend_verification'
                WHEN 10 THEN 'user.email_change_requested'
                WHEN 11 THEN 'user.email_changed'
                WHEN 12 THEN 'user.master_password_changed'
                WHEN 13 THEN 'user.logout'
                WHEN 14 THEN 'session.revoked'
                WHEN 15 THEN '2fa.setup.initiated'
                WHEN 16 THEN '2fa.enabled'
                WHEN 17 THEN '2fa.disabled'
                WHEN 18 THEN '2fa.backup_codes_regenerated'
                ELSE ''
            END
        ");

        $this->addSql('DROP INDEX idx_security_logs_action ON security_logs');
        $this->addSql('ALTER TABLE security_logs DROP COLUMN action');
        $this->addSql('ALTER TABLE security_logs CHANGE action_old action VARCHAR(100) NOT NULL');
        $this->addSql('CREATE INDEX idx_security_logs_action ON security_logs (action)');
    }
}
