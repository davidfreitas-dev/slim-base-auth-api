<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

class PasswordResetEmailTemplate extends AbstractEmailTemplate
{
    public function __construct(string $toEmail, string $recipientName, string $code)
    {
        $this->toEmail = $toEmail;
        $this->subject = 'Password Reset Request';
        $this->templateData = [
            'name' => $recipientName,
            'code' => $code,
        ];
    }

    public function getTemplatePath(): string
    {
        return __DIR__ . '/templates/password_reset.php';
    }
}
