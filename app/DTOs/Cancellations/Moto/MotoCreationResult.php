<?php

namespace App\DTOs\Cancellations\Moto;

use App\Models\MotoCancellation;
use App\Models\SmsAttempt;

final readonly class MotoCreationResult
{
    public function __construct(
        public bool $completed,
        public ?MotoCancellation $moto,
        public bool $created,
        public ?SmsAttempt $smsAttempt = null,
        public ?string $failureReason = null,
    ) {}

    public static function failed(string $reason): self
    {
        return new self(false, null, false, failureReason: $reason);
    }

    public static function completed(MotoCancellation $moto, bool $created, ?SmsAttempt $smsAttempt = null): self
    {
        return new self(true, $moto, $created, $smsAttempt);
    }
}
