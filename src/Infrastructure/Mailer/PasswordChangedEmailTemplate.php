<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

class PasswordChangedEmailTemplate extends AbstractEmailTemplate
{
    public function __construct(string $toEmail, string $recipientName)
    {
        $this->toEmail = $toEmail;
        $this->subject = 'Your Password Has Been Changed';
        $this->templateData = [
            'name' => $recipientName,
        ];
    }

    public function getTemplatePath(): string
    {
        return __DIR__ . '/templates/password_changed.php';
    }
}
