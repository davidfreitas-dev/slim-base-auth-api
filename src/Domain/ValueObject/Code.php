<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use Assert\Assertion;
use Assert\AssertionFailedException;
use InvalidArgumentException;

final readonly class Code implements \Stringable
{
    private function __construct(public string $value)
    {
        try {
            Assertion::regex($this->value, '/^\d{6}$/', 'Code must be a 6-digit number.');
        } catch (AssertionFailedException $assertionFailedException) {
            throw new InvalidArgumentException($assertionFailedException->getMessage(), $assertionFailedException->getCode(), $assertionFailedException);
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function from(string $value): self
    {
        return new self($value);
    }

    public static function generate(): self
    {
        return new self((string)\random_int(100000, 999999));
    }
}
