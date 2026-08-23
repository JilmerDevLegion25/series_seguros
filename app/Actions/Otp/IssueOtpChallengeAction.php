<?php

namespace App\Actions\Otp;

use App\DTOs\Otp\IssueOtpChallengeData;
use App\DTOs\Otp\OtpEmissionResult;
use App\Enums\SmsPurpose;
use App\Models\OtpChallenge;
use App\Services\Clock\Clock;
use App\Services\Otp\OtpCodeGenerator;
use App\Services\Otp\OtpMac;
use App\Services\Otp\OtpMessageFactory;
use App\Services\Otp\OtpPayloadCipher;
use App\Services\Otp\OtpReferenceGenerator;
use App\Services\Sms\SmsGateway;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final readonly class IssueOtpChallengeAction
{
    public function __construct(
        private OtpCodeGenerator $otpCodeGenerator,
        private OtpMac $otpMac,
        private OtpPayloadCipher $payloadCipher,
        private OtpReferenceGenerator $referenceGenerator,
        private OtpMessageFactory $messageFactory,
        private SmsGateway $smsGateway,
        private Clock $clock,
    ) {}

    public function execute(IssueOtpChallengeData $data): OtpEmissionResult
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $reference = $this->referenceGenerator->generate();
            $otp = $this->otpCodeGenerator->generate();

            try {
                $challenge = DB::transaction(function () use ($data, $reference, $otp): OtpChallenge {
                    $now = $this->clock->now();
                    $emissionCount = 1;

                    return OtpChallenge::query()->create([
                        'public_reference' => $reference,
                        'purpose' => $data->purpose,
                        'target_user_id' => $data->targetUser?->id,
                        'destination_snapshot' => $data->destination,
                        'encrypted_payload' => $this->encryptPayload($data->payload),
                        'otp_mac' => $this->otpMac->make($otp, $this->macContext($reference, $data->purpose->value, $emissionCount)),
                        'failed_attempts' => 0,
                        'emission_count' => $emissionCount,
                        'last_emitted_at' => $now,
                        'expires_at' => $now->addSeconds((int) config('otp.ttl_seconds')),
                    ]);
                });

                $deliveryResult = $this->smsGateway->send(
                    $challenge->destination_snapshot,
                    $this->messageFactory->make($otp, $challenge->purpose),
                    SmsPurpose::OTP,
                );

                return new OtpEmissionResult($challenge, $deliveryResult);
            } catch (QueryException $exception) {
                if ($attempt === 5) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Unable to create OTP challenge.');
    }

    public function macContext(string $publicReference, string $purpose, int $emissionCount): string
    {
        return $purpose.'|'.$publicReference.'|'.$emissionCount;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     *
     * @throws JsonException
     */
    private function encryptPayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        return $this->payloadCipher->encrypt($payload);
    }
}
