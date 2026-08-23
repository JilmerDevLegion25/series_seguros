<?php

namespace App\Services\Sms;

use App\Enums\SmsAttemptStatus;
use InvalidArgumentException;

final readonly class SmsGatewayResult
{
    private function __construct(
        public SmsAttemptStatus $status,
        public ?string $providerReference = null,
        public ?string $safeErrorCode = null,
        public ?string $safeErrorMessage = null,
    ) {
        if ($this->status === SmsAttemptStatus::PENDING) {
            throw new InvalidArgumentException('Gateway result cannot be PENDING.');
        }
    }

    public static function sent(?string $providerReference = null): self
    {
        return new self(SmsAttemptStatus::SENT, providerReference: $providerReference);
    }

    public static function failed(?string $safeErrorCode = null, ?string $safeErrorMessage = null): self
    {
        return new self(
            SmsAttemptStatus::FAILED,
            safeErrorCode: $safeErrorCode,
            safeErrorMessage: $safeErrorMessage,
        );
    }

    public static function unknown(?string $safeErrorCode = null, ?string $safeErrorMessage = null): self
    {
        return new self(
            SmsAttemptStatus::UNKNOWN,
            safeErrorCode: $safeErrorCode,
            safeErrorMessage: $safeErrorMessage,
        );
    }
}
