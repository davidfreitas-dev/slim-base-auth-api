<?php

declare(strict_types=1);

namespace App\Domain\Enum;

/**
 * Defines standardized keys for JSON responses.
 */
enum JsonResponseKey: string
{
    // Common keys
    case STATUS = 'status';
    case MESSAGE = 'message';
    case DATA = 'data';
    case ERRORS = 'errors';

    // Token related keys
    case ACCESS_TOKEN = 'access_token';
    case REFRESH_TOKEN = 'refresh_token';
    case TOKEN_TYPE = 'token_type';
    case EXPIRES_IN = 'expires_in';

    // Debugging keys
    case DEBUG = 'debug';
    case FILE = 'file';
    case LINE = 'line';
    case TRACE = 'trace';
}
