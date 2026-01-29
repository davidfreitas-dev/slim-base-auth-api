<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Mailer;

use App\Infrastructure\Mailer\PasswordChangedEmailTemplate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Mailer\PasswordChangedEmailTemplate
 */
final class PasswordChangedEmailTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createEmailTemplate(
        string $toEmail = 'test@example.com',
        string $recipientName = 'Test User'
    ): PasswordChangedEmailTemplate {
        return new PasswordChangedEmailTemplate(
            $toEmail,
            $recipientName
        );
    }

    public function testConstructorSetsProperties(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame('test@example.com', $emailTemplate->getToEmail());
        self::assertSame('Your Password Has Been Changed', $emailTemplate->getSubject());
        self::assertSame('Test User', $emailTemplate->getToName());

        $templateData = $emailTemplate->getTemplateData();
        self::assertArrayHasKey('name', $templateData);
        self::assertSame('Test User', $templateData['name']);
    }

    public function testGetTemplatePath(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        $expectedPath = dirname(__DIR__, 4) . '/src/Infrastructure/Mailer/templates/password_changed.php';
        self::assertSame($expectedPath, $emailTemplate->getTemplatePath());
    }
}