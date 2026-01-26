<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

class EmailVerificationEmailTemplate extends AbstractEmailTemplate
{
    public function __construct(
        string $toEmail,
        string $toName,
        private readonly string $verificationLink,
    ) {
        $this->toEmail = $toEmail;
        $this->subject = 'Verify Your Email Address';
        $this->templateData = [
            'name' => $toName,
            'verificationLink' => $verificationLink,
        ];
    }

    public function getTemplatePath(): string
    {
        return __DIR__ . '/templates/email_verification.php';
    }

    public function getVerificationLink(): string
    {
        return $this->verificationLink;
    }
}
