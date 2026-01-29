<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Mailer;

use App\Infrastructure\Mailer\PasswordResetEmailTemplate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Mailer\PasswordResetEmailTemplate
 */
final class PasswordResetEmailTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createEmailTemplate(
        string $toEmail = 'test@example.com',
        string $recipientName = 'Test User',
        string $code = '12345'
    ): PasswordResetEmailTemplate {
        return new PasswordResetEmailTemplate(
            $toEmail,
            $recipientName,
            $code
        );
    }

    public function testConstructorSetsProperties(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame('test@example.com', $emailTemplate->getToEmail());
        self::assertSame('Password Reset Request', $emailTemplate->getSubject());
        self::assertSame('Test User', $emailTemplate->getToName());

        $templateData = $emailTemplate->getTemplateData();
        self::assertArrayHasKey('name', $templateData);
        self::assertArrayHasKey('code', $templateData);
        self::assertSame('Test User', $templateData['name']);
        self::assertSame('12345', $templateData['code']);
    }

    public function testGetTemplatePath(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        $expectedPath = dirname(__DIR__, 4) . '/src/Infrastructure/Mailer/templates/password_reset.php';
        self::assertSame($expectedPath, $emailTemplate->getTemplatePath());
    }
}