<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Defines standardized status values for JSON responses.
 */
enum JsonResponseStatus: string
{
    case SUCCESS = 'success';
    case FAIL = 'fail';
    case ERROR = 'error';
}
