<?php

declare(strict_types=1);

namespace App\Service\Email;

class EmailTemplateService
{
    public function renderHtml(string $template, array $context = []): string
    {
        return match ($template) {
            'email_verification'        => $this->renderEmailVerification($context),
            'email_change_confirmation' => $this->renderEmailChangeConfirmation($context),
            'security_new_login'        => $this->renderSecurityNewLogin($context),
            'security_password_changed' => $this->renderSecurityPasswordChanged($context),
            'security_account_deleted'  => $this->renderSecurityAccountDeleted($context),
            'security_email_changed'    => $this->renderSecurityEmailChanged($context),
            default => throw new \InvalidArgumentException("Unknown email template: $template"),
        };
    }

    private function renderEmailChangeConfirmation(array $context): string
    {
        $link     = htmlspecialchars($context['confirmation_link'] ?? '', ENT_QUOTES, 'UTF-8');
        $newEmail = htmlspecialchars($context['new_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $hours    = (int) ($context['expires_hours'] ?? 1);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Подтверждение смены email — PassFort</title>
        </head>
        <body style="margin:0;padding:0;background-color:#0f172a;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#1e293b;border-radius:8px;overflow:hidden;">
                            <tr>
                                <td style="background-color:#1d4ed8;padding:24px 32px;">
                                    <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">PassFort</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px;">
                                    <h2 style="margin:0 0 16px;color:#f1f5f9;font-size:20px;">Подтвердите смену email</h2>
                                    <p style="margin:0 0 24px;color:#94a3b8;font-size:15px;line-height:1.6;">
                                        Поступил запрос на смену email аккаунта PassFort на адрес
                                        <strong style="color:#e2e8f0;">{$newEmail}</strong>.
                                        Нажмите кнопку ниже для подтверждения. Ссылка действительна {$hours} час(ов).
                                    </p>
                                    <table cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="border-radius:6px;background-color:#1d4ed8;">
                                                <a href="{$link}"
                                                   style="display:inline-block;padding:14px 28px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;border-radius:6px;">
                                                    Подтвердить смену email
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:24px 0 0;color:#64748b;font-size:13px;line-height:1.5;">
                                        Если кнопка не работает, скопируйте эту ссылку в браузер:<br>
                                        <span style="color:#93c5fd;word-break:break-all;">{$link}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 32px;border-top:1px solid #334155;">
                                    <p style="margin:0;color:#64748b;font-size:12px;">
                                        Если вы не запрашивали смену email — просто проигнорируйте это письмо.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }

    private function renderSecurityEmailChanged(array $context): string
    {
        $newEmail = htmlspecialchars($context['new_email'] ?? '', ENT_QUOTES, 'UTF-8');
        $ip       = htmlspecialchars($context['ip'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        $device   = htmlspecialchars($context['device'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        $datetime = htmlspecialchars($context['datetime'] ?? '', ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email изменён — PassFort</title>
        </head>
        <body style="margin:0;padding:0;background-color:#0f172a;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#1e293b;border-radius:8px;overflow:hidden;">
                            <tr>
                                <td style="background-color:#b45309;padding:24px 32px;">
                                    <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">PassFort</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px;">
                                    <h2 style="margin:0 0 16px;color:#f1f5f9;font-size:20px;">Email аккаунта изменён</h2>
                                    <p style="margin:0 0 24px;color:#94a3b8;font-size:15px;line-height:1.6;">
                                        Email вашего аккаунта PassFort был изменён на
                                        <strong style="color:#e2e8f0;">{$newEmail}</strong>.
                                    </p>
                                    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;border-radius:6px;margin-bottom:24px;">
                                        <tr>
                                            <td style="padding:16px 20px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">IP-адрес</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:15px;">{$ip}</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 20px 16px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">Устройство</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:13px;word-break:break-all;">{$device}</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 20px 16px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">Время</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:15px;">{$datetime}</p>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0;color:#fbbf24;font-size:14px;line-height:1.6;font-weight:600;">
                                        Если вы не меняли email — ваш аккаунт мог быть скомпрометирован. Немедленно свяжитесь со службой поддержки.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 32px;border-top:1px solid #334155;">
                                    <p style="margin:0;color:#64748b;font-size:12px;">
                                        Это автоматическое уведомление безопасности PassFort.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }

    private function renderSecurityNewLogin(array $context): string
    {
        $ip       = htmlspecialchars($context['ip'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        $device   = htmlspecialchars($context['device'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        $datetime = htmlspecialchars($context['datetime'] ?? '', ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Новый вход — PassFort</title>
        </head>
        <body style="margin:0;padding:0;background-color:#0f172a;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#1e293b;border-radius:8px;overflow:hidden;">
                            <tr>
                                <td style="background-color:#1d4ed8;padding:24px 32px;">
                                    <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">PassFort</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px;">
                                    <h2 style="margin:0 0 16px;color:#f1f5f9;font-size:20px;">Новый вход в аккаунт</h2>
                                    <p style="margin:0 0 24px;color:#94a3b8;font-size:15px;line-height:1.6;">
                                        Зафиксирован вход в ваш аккаунт PassFort.
                                        Если это были вы — никаких действий не требуется.
                                    </p>
                                    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;border-radius:6px;margin-bottom:24px;">
                                        <tr>
                                            <td style="padding:16px 20px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">IP-адрес</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:15px;">{$ip}</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 20px 16px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">Устройство</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:13px;word-break:break-all;">{$device}</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 20px 16px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">Время</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:15px;">{$datetime}</p>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0;color:#94a3b8;font-size:14px;line-height:1.6;">
                                        Если вы не выполняли вход — немедленно смените мастер-пароль и отзовите все активные сессии в настройках аккаунта.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 32px;border-top:1px solid #334155;">
                                    <p style="margin:0;color:#64748b;font-size:12px;">
                                        Это автоматическое уведомление безопасности PassFort.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }

    private function renderSecurityPasswordChanged(array $context): string
    {
        $ip       = htmlspecialchars($context['ip'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        $datetime = htmlspecialchars($context['datetime'] ?? '', ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Мастер-пароль изменён — PassFort</title>
        </head>
        <body style="margin:0;padding:0;background-color:#0f172a;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#1e293b;border-radius:8px;overflow:hidden;">
                            <tr>
                                <td style="background-color:#b45309;padding:24px 32px;">
                                    <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">PassFort</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px;">
                                    <h2 style="margin:0 0 16px;color:#f1f5f9;font-size:20px;">Мастер-пароль изменён</h2>
                                    <p style="margin:0 0 24px;color:#94a3b8;font-size:15px;line-height:1.6;">
                                        Мастер-пароль вашего аккаунта PassFort был изменён. Все активные сессии были завершены.
                                    </p>
                                    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;border-radius:6px;margin-bottom:24px;">
                                        <tr>
                                            <td style="padding:16px 20px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">IP-адрес</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:15px;">{$ip}</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 20px 16px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">Время</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:15px;">{$datetime}</p>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0;color:#fbbf24;font-size:14px;line-height:1.6;font-weight:600;">
                                        Если вы не меняли мастер-пароль — ваш аккаунт мог быть скомпрометирован. Обратитесь в службу поддержки немедленно.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 32px;border-top:1px solid #334155;">
                                    <p style="margin:0;color:#64748b;font-size:12px;">
                                        Это автоматическое уведомление безопасности PassFort.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }

    private function renderSecurityAccountDeleted(array $context): string
    {
        $ip       = htmlspecialchars($context['ip'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        $datetime = htmlspecialchars($context['datetime'] ?? '', ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Аккаунт удалён — PassFort</title>
        </head>
        <body style="margin:0;padding:0;background-color:#0f172a;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#1e293b;border-radius:8px;overflow:hidden;">
                            <tr>
                                <td style="background-color:#991b1b;padding:24px 32px;">
                                    <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">PassFort</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px;">
                                    <h2 style="margin:0 0 16px;color:#f1f5f9;font-size:20px;">Аккаунт удалён</h2>
                                    <p style="margin:0 0 24px;color:#94a3b8;font-size:15px;line-height:1.6;">
                                        Ваш аккаунт PassFort и все связанные данные были безвозвратно удалены.
                                    </p>
                                    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;border-radius:6px;margin-bottom:24px;">
                                        <tr>
                                            <td style="padding:16px 20px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">IP-адрес</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:15px;">{$ip}</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding:0 20px 16px;">
                                                <p style="margin:0 0 8px;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;">Время</p>
                                                <p style="margin:0;color:#e2e8f0;font-size:15px;">{$datetime}</p>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:0;color:#f87171;font-size:14px;line-height:1.6;font-weight:600;">
                                        Если вы не удаляли аккаунт — ваш аккаунт мог быть скомпрометирован. Восстановление данных невозможно.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 32px;border-top:1px solid #334155;">
                                    <p style="margin:0;color:#64748b;font-size:12px;">
                                        Это автоматическое уведомление безопасности PassFort.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }

    private function renderEmailVerification(array $context): string
    {
        $link = htmlspecialchars($context['verification_link'] ?? '', ENT_QUOTES, 'UTF-8');
        $hours = (int) ($context['expires_hours'] ?? 24);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Подтверждение email — PassFort</title>
        </head>
        <body style="margin:0;padding:0;background-color:#0f172a;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0f172a;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background-color:#1e293b;border-radius:8px;overflow:hidden;">
                            <tr>
                                <td style="background-color:#1d4ed8;padding:24px 32px;">
                                    <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">🔒 PassFort</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px;">
                                    <h2 style="margin:0 0 16px;color:#f1f5f9;font-size:20px;">Подтвердите ваш email</h2>
                                    <p style="margin:0 0 24px;color:#94a3b8;font-size:15px;line-height:1.6;">
                                        Для завершения регистрации в PassFort нажмите кнопку ниже.
                                        Ссылка действительна {$hours} часа(ов).
                                    </p>
                                    <table cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="border-radius:6px;background-color:#1d4ed8;">
                                                <a href="{$link}"
                                                   style="display:inline-block;padding:14px 28px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;border-radius:6px;">
                                                    Подтвердить email
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin:24px 0 0;color:#64748b;font-size:13px;line-height:1.5;">
                                        Если кнопка не работает, скопируйте эту ссылку в браузер:<br>
                                        <span style="color:#93c5fd;word-break:break-all;">{$link}</span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 32px;border-top:1px solid #334155;">
                                    <p style="margin:0;color:#64748b;font-size:12px;">
                                        Если вы не регистрировались в PassFort — просто проигнорируйте это письмо.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
