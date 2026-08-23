<?php

namespace App\Actions\Otp;

use App\Exceptions\OtpChallengeException;
use App\Models\OtpChallenge;
use App\Services\Otp\OtpPayloadCipher;
use JsonException;
use RuntimeException;

final readonly class DecryptOtpPayloadAction
{
    public function __construct(
        private OtpPayloadCipher $payloadCipher,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function execute(OtpChallenge $challenge): array
    {
        if ($challenge->isTerminal() || $challenge->encrypted_payload === null) {
            throw OtpChallengeException::notFoundOrUnavailable();
        }

        $payload = $this->payloadCipher->decrypt($challenge->encrypted_payload);

        if ($payload === []) {
            throw new RuntimeException('OTP payload is empty.');
        }

        return $payload;
    }
}
