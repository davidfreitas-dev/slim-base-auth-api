<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

class RegisterUserEmailTemplate extends AbstractEmailTemplate
{
    public function __construct(string $toEmail, string $recipientName)
    {
        $this->toEmail = $toEmail;
        $this->subject = 'Welcome to Our Application!';
        $this->templateData = [
            'name' => $recipientName,
        ];
    }

    public function getTemplatePath(): string
    {
        return __DIR__ . '/templates/register_user.php';
    }
}
