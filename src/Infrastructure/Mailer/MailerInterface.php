<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Exception\EmailSendingFailedException;

interface MailerInterface
{
    /**
     * Sends a general email using a specified template.
     *
     * @param AbstractEmailTemplate $template the email template object containing recipient, subject, and data
     *
     * @throws EmailSendingFailedException if the email could not be sent
     */
    public function send(AbstractEmailTemplate $template): void;
}
