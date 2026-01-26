<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

abstract class AbstractEmailTemplate
{
    protected string $toEmail;
    protected string $subject;
    protected array $templateData = [];

    public function getToEmail(): string
    {
        return $this->toEmail;
    }

    public function getToName(): ?string
    {
        return $this->templateData['name'] ?? null;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    abstract public function getTemplatePath(): string;
}
