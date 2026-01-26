<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Exception\EmailSendingFailedException;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Psr\Log\LoggerInterface;
use Throwable;

use function extract;

class PHPMailerService implements MailerInterface
{
    private PHPMailer $mailer;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $smtpHost,
        private readonly int $smtpPort,
        private readonly string $smtpUsername,
        private readonly string $smtpPassword,
        private readonly string $smtpEncryption,
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly string $siteUrl,
        private readonly string $appName,
    ) {
        $this->mailer = new PHPMailer(true); // true enables exceptions
        $this->configureMailer();
    }

    /**
     * Sends a general email using a specified template.
     *
     * @param AbstractEmailTemplate $template the email template object containing recipient, subject, and data
     *
     * @throws EmailSendingFailedException if the email could not be sent
     */
    public function send(AbstractEmailTemplate $template): void
    {
        try {
            $this->mailer->clearAllRecipients(); // Clear recipients from previous sends
            $this->mailer->addAddress($template->getToEmail(), $template->getToName() ?? '');
            $this->mailer->Subject = $template->getSubject();
            $this->mailer->Body = $this->renderHtmlTemplate($template);
            $this->mailer->AltBody = \strip_tags($this->mailer->Body); // A simple plain-text alternative

            $this->mailer->send();
            $this->logger->info(sprintf("Email '%s' sent to %s", $template->getTemplatePath(), $template->getToEmail()));
        } catch (PHPMailerException $e) {
            $this->logger->error(sprintf("Failed to send email '%s' to %s: %s", $template->getTemplatePath(), $template->getToEmail(), $e->getMessage()));

            throw new EmailSendingFailedException('Failed to send email: ' . $e->getMessage(), (int)$e->getCode(), $e);
        } catch (Throwable $e) {
            $this->logger->error(sprintf("An unexpected error occurred while sending email '%s' to %s: %s", $template->getTemplatePath(), $template->getToEmail(), $e->getMessage()));

            throw new EmailSendingFailedException('An unexpected error occurred: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    private function configureMailer(): void
    {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->smtpHost;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->smtpUsername;
            $this->mailer->Password = $this->smtpPassword;
            $this->mailer->SMTPSecure = $this->smtpEncryption;
            $this->mailer->Port = $this->smtpPort;
            // $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
            // $this->mailer->Debugoutput = 'stderr';         // Output debug info to stderr
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;
        } catch (PHPMailerException $phpMailerException) {
            $this->logger->error('Failed to configure PHPMailer: ' . $phpMailerException->getMessage());

            throw new EmailSendingFailedException('Mailer configuration error: ' . $phpMailerException->getMessage(), (int)$phpMailerException->getCode(), $phpMailerException);
        }
    }

    /**
     * Renders the HTML email template with dynamic content.
     *
     * This method uses a two-step rendering process:
     * 1. It renders a content-specific template (e.g., 'PasswordResetEmailTemplate.php').
     * 2. It injects the resulting HTML into a main layout template ('EmailTemplate.php').
     *
     * @param AbstractEmailTemplate $abstractEmailTemplate The email template object containing recipient, subject, and data.
     *
     * @throws EmailSendingFailedException if a template file is not found
     *
     * @return string the fully rendered HTML email body
     */
    private function renderHtmlTemplate(AbstractEmailTemplate $abstractEmailTemplate): string
    {
        // Define paths for content and layout templates
        $contentTemplatePath = $abstractEmailTemplate->getTemplatePath();
        $layoutTemplatePath = __DIR__ . '/templates/layout.php';

        // Ensure the content template file exists
        if (!\file_exists($contentTemplatePath)) {
            $this->logger->error('Email content template not found: ' . $contentTemplatePath);

            throw new EmailSendingFailedException('Email content template not found: ' . $contentTemplatePath);
        }

        // Ensure the layout template file exists
        if (!\file_exists($layoutTemplatePath)) {
            $this->logger->error('Email layout template not found: ' . $layoutTemplatePath);

            throw new EmailSendingFailedException('Email layout template not found.');
        }

        $data = $abstractEmailTemplate->getTemplateData();
        // Add framework-level variables to the data array
        $data['siteUrl'] = $this->siteUrl;
        $data['appName'] = $this->appName;
        $data['year'] = \date('Y');
        $data['subject'] = $abstractEmailTemplate->getSubject();

        // Extract data for the content template
        \extract($data);

        // Render the content template
        \ob_start();

        include $contentTemplatePath;
        $contentHtml = \ob_get_clean();

        // Render the main layout, passing the rendered content and other variables
        \ob_start();

        include $layoutTemplatePath;

        return \ob_get_clean();
    }
}
