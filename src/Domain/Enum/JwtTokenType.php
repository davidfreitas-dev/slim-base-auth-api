<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Defines the types for JWT tokens.
 */
enum JwtTokenType: string
{
    case ACCESS = 'access';
    case REFRESH = 'refresh';
}
