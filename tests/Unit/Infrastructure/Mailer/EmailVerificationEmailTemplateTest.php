<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Mailer;

use App\Infrastructure\Mailer\EmailVerificationEmailTemplate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Mailer\EmailVerificationEmailTemplate
 */
final class EmailVerificationEmailTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createEmailTemplate(
        string $toEmail = 'test@example.com',
        string $toName = 'Test User',
        string $verificationLink = 'http://localhost/verify?code=12345'
    ): EmailVerificationEmailTemplate {
        return new EmailVerificationEmailTemplate(
            $toEmail,
            $toName,
            $verificationLink
        );
    }

    public function testConstructorSetsProperties(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame('test@example.com', $emailTemplate->getToEmail());
        self::assertSame('Verify Your Email Address', $emailTemplate->getSubject());
        self::assertSame('Test User', $emailTemplate->getToName());
        self::assertSame('http://localhost/verify?code=12345', $emailTemplate->getVerificationLink());

        $templateData = $emailTemplate->getTemplateData();
        self::assertArrayHasKey('name', $templateData);
        self::assertArrayHasKey('verificationLink', $templateData);
        self::assertSame('Test User', $templateData['name']);
        self::assertSame('http://localhost/verify?code=12345', $templateData['verificationLink']);
    }

    public function testGetTemplatePath(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        $expectedPath = dirname(__DIR__, 4) . '/src/Infrastructure/Mailer/templates/email_verification.php';
        self::assertSame($expectedPath, $emailTemplate->getTemplatePath());
    }

    public function testGetVerificationLink(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame('http://localhost/verify?code=12345', $emailTemplate->getVerificationLink());
    }
}