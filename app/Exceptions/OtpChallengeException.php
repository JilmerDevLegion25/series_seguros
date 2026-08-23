<?php

namespace App\Exceptions;

use RuntimeException;

final class OtpChallengeException extends RuntimeException
{
    public static function notFoundOrUnavailable(): self
    {
        return new self('OTP challenge is not available.');
    }

    public static function cooldownActive(): self
    {
        return new self('OTP resend cooldown is active.');
    }

    public static function emissionLimitReached(): self
    {
        return new self('OTP emission limit reached.');
    }

    public static function invalidPurpose(): self
    {
        return new self('OTP purpose is not allowed for this operation.');
    }
}
