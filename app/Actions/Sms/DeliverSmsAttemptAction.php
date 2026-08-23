<?php

namespace App\Actions\Sms;

use App\Enums\SmsAttemptStatus;
use App\Models\SmsAttempt;
use App\Services\Sms\SmsGateway;
use Throwable;

final readonly class DeliverSmsAttemptAction
{
    public function __construct(
        private SmsGateway $smsGateway,
    ) {}

    public function execute(SmsAttempt $attempt, string $message): SmsAttempt
    {
        try {
            $result = $this->smsGateway->send($attempt->destination, $message, $attempt->purpose);
        } catch (Throwable $throwable) {
            $attempt->forceFill([
                'status' => SmsAttemptStatus::UNKNOWN,
                'safe_error_code' => 'PROVIDER_EXCEPTION',
                'safe_error_message' => 'Provider error.',
                'completed_at' => now(),
            ])->save();

            return $attempt;
        }

        $attempt->forceFill([
            'status' => $result->status,
            'provider_reference' => $result->providerReference,
            'safe_error_code' => $result->safeErrorCode,
            'safe_error_message' => $result->safeErrorMessage,
            'completed_at' => now(),
        ])->save();

        return $attempt;
    }
}
