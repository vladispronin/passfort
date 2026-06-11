<?php

declare(strict_types=1);

namespace App\Enum;

enum SecurityLogAction: int
{
    case USER_REGISTER                   = 1;
    case USER_LOGIN                      = 2;
    case USER_LOGIN_FAILED               = 3;
    case USER_LOGIN_EMAIL_NOT_VERIFIED   = 4;
    case USER_LOGIN_2FA_REQUIRED         = 5;
    case USER_LOGIN_2FA_SUCCESS          = 6;
    case USER_LOGIN_2FA_FAILED           = 7;
    case USER_EMAIL_VERIFIED             = 8;
    case USER_RESEND_VERIFICATION        = 9;
    case USER_EMAIL_CHANGE_REQUESTED     = 10;
    case USER_EMAIL_CHANGED              = 11;
    case USER_MASTER_PASSWORD_CHANGED    = 12;
    case USER_LOGOUT                     = 13;
    case SESSION_REVOKED                 = 14;
    case TWO_FA_SETUP_INITIATED          = 15;
    case TWO_FA_ENABLED                  = 16;
    case TWO_FA_DISABLED                 = 17;
    case TWO_FA_BACKUP_CODES_REGENERATED = 18;

    public function label(): string
    {
        return match ($this) {
            self::USER_REGISTER                   => 'user.register',
            self::USER_LOGIN                      => 'user.login',
            self::USER_LOGIN_FAILED               => 'user.login.failed',
            self::USER_LOGIN_EMAIL_NOT_VERIFIED   => 'user.login.email_not_verified',
            self::USER_LOGIN_2FA_REQUIRED         => 'user.login.2fa_required',
            self::USER_LOGIN_2FA_SUCCESS          => 'user.login.2fa_success',
            self::USER_LOGIN_2FA_FAILED           => 'user.login.2fa_failed',
            self::USER_EMAIL_VERIFIED             => 'user.email_verified',
            self::USER_RESEND_VERIFICATION        => 'user.resend_verification',
            self::USER_EMAIL_CHANGE_REQUESTED     => 'user.email_change_requested',
            self::USER_EMAIL_CHANGED              => 'user.email_changed',
            self::USER_MASTER_PASSWORD_CHANGED    => 'user.master_password_changed',
            self::USER_LOGOUT                     => 'user.logout',
            self::SESSION_REVOKED                 => 'session.revoked',
            self::TWO_FA_SETUP_INITIATED          => '2fa.setup.initiated',
            self::TWO_FA_ENABLED                  => '2fa.enabled',
            self::TWO_FA_DISABLED                 => '2fa.disabled',
            self::TWO_FA_BACKUP_CODES_REGENERATED => '2fa.backup_codes_regenerated',
        };
    }
}
