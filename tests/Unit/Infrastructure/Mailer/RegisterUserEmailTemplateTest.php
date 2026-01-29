<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Mailer;

use App\Infrastructure\Mailer\RegisterUserEmailTemplate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Mailer\RegisterUserEmailTemplate
 */
final class RegisterUserEmailTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createEmailTemplate(
        string $toEmail = 'test@example.com',
        string $recipientName = 'Test User'
    ): RegisterUserEmailTemplate {
        return new RegisterUserEmailTemplate(
            $toEmail,
            $recipientName
        );
    }

    public function testConstructorSetsProperties(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame('test@example.com', $emailTemplate->getToEmail());
        self::assertSame('Welcome to Our Application!', $emailTemplate->getSubject());
        self::assertSame('Test User', $emailTemplate->getToName());

        $templateData = $emailTemplate->getTemplateData();
        self::assertArrayHasKey('name', $templateData);
        self::assertSame('Test User', $templateData['name']);
    }

    public function testGetTemplatePath(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        $expectedPath = dirname(__DIR__, 4) . '/src/Infrastructure/Mailer/templates/register_user.php';
        self::assertSame($expectedPath, $emailTemplate->getTemplatePath());
    }
}