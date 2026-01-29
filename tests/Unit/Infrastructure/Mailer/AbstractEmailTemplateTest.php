<?php

declare(strict_types=1);

namespace App\Test\Unit\Infrastructure\Mailer;

use App\Infrastructure\Mailer\AbstractEmailTemplate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Infrastructure\Mailer\AbstractEmailTemplate
 */
final class AbstractEmailTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createEmailTemplate(string $toEmail = 'test@example.com', string $subject = 'Test Subject', array $templateData = ['name' => 'John Doe', 'key' => 'value']): AbstractEmailTemplate
    {
        return new class($toEmail, $subject, $templateData) extends AbstractEmailTemplate {
            public function __construct(string $toEmail, string $subject, array $templateData)
            {
                $this->toEmail = $toEmail;
                $this->subject = $subject;
                $this->templateData = $templateData;
            }

            public function getTemplatePath(): string
            {
                return 'test_template.html';
            }
        };
    }

    public function testGetToEmail(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame('test@example.com', $emailTemplate->getToEmail());
    }

    public function testGetToName(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame('John Doe', $emailTemplate->getToName());
    }

    public function testGetToNameReturnsNullWhenNotSet(): void
    {
        $emailTemplate = $this->createEmailTemplate(templateData: ['key' => 'value']); // 'name' is not set
        self::assertNull($emailTemplate->getToName());
    }

    public function testGetSubject(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame('Test Subject', $emailTemplate->getSubject());
    }

    public function testGetTemplateData(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame(['name' => 'John Doe', 'key' => 'value'], $emailTemplate->getTemplateData());
    }

    public function testGetTemplatePath(): void
    {
        $emailTemplate = $this->createEmailTemplate();
        self::assertSame('test_template.html', $emailTemplate->getTemplatePath());
    }
}