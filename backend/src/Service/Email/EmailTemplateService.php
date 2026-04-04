<?php

declare(strict_types=1);

namespace App\Service\Email;

class EmailTemplateService
{
    public function renderHtml(string $template, array $context = []): string
    {
        return match ($template) {
            'email_verification' => $this->renderEmailVerification($context),
            default => throw new \InvalidArgumentException("Unknown email template: $template"),
        };
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
