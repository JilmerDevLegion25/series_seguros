<?php

namespace App\Exceptions;

use RuntimeException;

final class SmsRetryException extends RuntimeException
{
    public static function rateLimited(): self
    {
        return new self('SMS_RETRY_RATE_LIMITED');
    }

    public static function limitExceeded(): self
    {
        return new self('SMS_RETRY_LIMIT_EXCEEDED');
    }

    public static function cooldown(): self
    {
        return new self('SMS_RETRY_COOLDOWN');
    }
}
