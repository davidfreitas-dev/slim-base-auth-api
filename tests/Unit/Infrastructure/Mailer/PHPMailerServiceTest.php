<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mailer;

use App\Application\Exception\EmailSendingFailedException;
use App\Infrastructure\Mailer\AbstractEmailTemplate;
use App\Infrastructure\Mailer\PHPMailerService;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Tests\TestCase;

class PHPMailerServiceTest extends TestCase
{
    private MockObject|LoggerInterface $logger;
    private MockObject|PHPMailer $mailerMock;
    private PHPMailerService $mailerService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock Logger and PHPMailer
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mailerMock = $this->getMockBuilder(PHPMailer::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Instantiate the service
        $this->mailerService = new PHPMailerService(
            $this->logger,
            'smtp.example.com',
            587,
            'user',
            'pass',
            'tls',
            'from@example.com',
            'Test Sender',
            'http://localhost',
            'Test App'
        );

        // Use reflection to replace the mailer instance with our mock
        $reflection = new ReflectionClass($this->mailerService);
        $mailerProperty = $reflection->getProperty('mailer');

        $mailerProperty->setValue($this->mailerService, $this->mailerMock);
    }

    public function testSendSuccessful(): void
    {
        // 1. Arrange
        $template = $this->createMock(AbstractEmailTemplate::class);
        $template->method('getToEmail')->willReturn('to@example.com');
        $template->method('getToName')->willReturn('Test Recipient');
        $template->method('getSubject')->willReturn('Test Subject');
        $template->method('getTemplatePath')->willReturn(__DIR__ . '/fixtures/test_template.php');
        $template->method('getTemplateData')->willReturn(['name' => 'John Doe']);

        $this->mailerMock->expects($this->once())->method('clearAllRecipients');
        $this->mailerMock->expects($this->once())->method('addAddress')->with('to@example.com', 'Test Recipient');
        $this->mailerMock->expects($this->once())->method('send');
        $this->logger->expects($this->once())->method('info');

        // 2. Act
        $this->mailerService->send($template);

        // 3. Assert (covered by expects)
    }

    public function testSendThrowsExceptionOnMailerError(): void
    {
        // 1. Arrange
        $this->expectException(EmailSendingFailedException::class);

        $template = $this->createMock(AbstractEmailTemplate::class);
        $template->method('getToEmail')->willReturn('to@example.com');
        $template->method('getTemplatePath')->willReturn(__DIR__ . '/fixtures/test_template.php');
        $template->method('getTemplateData')->willReturn(['name' => 'John Doe']);

        $this->mailerMock->method('send')->will($this->throwException(new PHPMailerException('SMTP Error')));
        $this->logger->expects($this->once())->method('error');

        // 2. Act
        $this->mailerService->send($template);
    }

    public function testSendThrowsExceptionOnUnexpectedError(): void
    {
        // 1. Arrange
        $this->expectException(EmailSendingFailedException::class);

        $template = $this->createMock(AbstractEmailTemplate::class);
        $template->method('getToEmail')->willReturn('to@example.com');
        $template->method('getTemplatePath')->willReturn(__DIR__ . '/fixtures/test_template.php');
        $template->method('getTemplateData')->willReturn(['name' => 'John Doe']);

        $this->mailerMock->method('send')->will($this->throwException(new \Exception('Unexpected Error')));
        $this->logger->expects($this->once())->method('error');

        // 2. Act
        $this->mailerService->send($template);
    }

    public function testRenderHtmlTemplateThrowsExceptionIfTemplateNotFound(): void
    {
        // 1. Arrange
        $this->expectException(EmailSendingFailedException::class);
        $this->expectExceptionMessage('Email content template not found');

        $template = $this->createMock(AbstractEmailTemplate::class);
        $template->method('getToEmail')->willReturn('to@example.com');
        $template->method('getTemplatePath')->willReturn('non_existent_template.php');

        // 2. Act
        $this->mailerService->send($template);
    }
}