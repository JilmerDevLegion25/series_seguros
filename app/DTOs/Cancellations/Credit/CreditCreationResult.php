<?php

namespace App\DTOs\Cancellations\Credit;

use App\Models\CreditCancellation;
use App\Models\SmsAttempt;

final readonly class CreditCreationResult
{
    public function __construct(
        public bool $completed,
        public ?CreditCancellation $credit,
        public bool $created,
        public ?SmsAttempt $smsAttempt = null,
        public ?string $failureReason = null,
    ) {}

    public static function failed(string $reason): self
    {
        return new self(false, null, false, failureReason: $reason);
    }

    public static function completed(CreditCancellation $credit, bool $created, ?SmsAttempt $smsAttempt = null): self
    {
        return new self(true, $credit, $created, $smsAttempt);
    }
}
