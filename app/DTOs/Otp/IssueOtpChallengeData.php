<?php

namespace App\DTOs\Otp;

use App\Enums\OtpPurpose;
use App\Models\User;

final readonly class IssueOtpChallengeData
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public OtpPurpose $purpose,
        public string $destination,
        public ?User $targetUser = null,
        public ?array $payload = null,
    ) {}
}
