<?php

namespace App\Services\Sms;

use App\Enums\SmsPurpose;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final readonly class InfobipSmsGateway implements SmsGateway
{
    public function __construct(
        private string $endpoint,
        private string $authorizationHeader,
        private string $from,
        private int $connectTimeoutSeconds,
        private int $totalTimeoutSeconds,
    ) {
        if (trim($this->endpoint) === '') {
            throw new InvalidArgumentException('SMS provider endpoint must be configured.');
        }

        if (trim($this->authorizationHeader) === '') {
            throw new InvalidArgumentException('SMS provider authorization must be configured.');
        }

        if (trim($this->from) === '') {
            throw new InvalidArgumentException('SMS provider sender must be configured.');
        }
    }

    public function send(string $destination, string $message, SmsPurpose $purpose): SmsGatewayResult
    {
        try {
            $response = Http::connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->totalTimeoutSeconds)
                ->withHeaders([
                    'Authorization' => $this->authorizationHeader,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->endpoint, [
                    'messages' => [
                        [
                            'destinations' => [
                                ['to' => $this->infobipDestination($destination)],
                            ],
                            'from' => $this->from,
                            'text' => $message,
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            return SmsGatewayResult::unknown(
                safeErrorCode: 'PROVIDER_CONNECTION_ERROR',
                safeErrorMessage: 'Provider response unavailable.',
            );
        }

        if (! $response->successful()) {
            return SmsGatewayResult::failed(
                safeErrorCode: 'PROVIDER_HTTP_'.$response->status(),
                safeErrorMessage: 'Provider rejected request.',
            );
        }

        return $this->resultFromSuccessfulResponse($response);
    }

    private function resultFromSuccessfulResponse(Response $response): SmsGatewayResult
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            return SmsGatewayResult::unknown('PROVIDER_INVALID_RESPONSE', 'Provider response unavailable.');
        }

        $firstMessage = data_get($payload, 'messages.0');

        if (! is_array($firstMessage)) {
            return SmsGatewayResult::sent();
        }

        $providerReference = data_get($firstMessage, 'messageId');
        $groupName = data_get($firstMessage, 'status.groupName');
        $normalizedGroupName = is_string($groupName) ? strtoupper($groupName) : null;

        if ($normalizedGroupName !== null && in_array($normalizedGroupName, ['REJECTED', 'UNDELIVERABLE', 'EXPIRED'], true)) {
            return SmsGatewayResult::failed(
                safeErrorCode: 'PROVIDER_'.$normalizedGroupName,
                safeErrorMessage: 'Provider rejected request.',
            );
        }

        return SmsGatewayResult::sent(
            is_scalar($providerReference) ? (string) $providerReference : null,
        );
    }

    private function infobipDestination(string $destination): string
    {
        $digits = preg_replace('/\D+/', '', $destination);

        if (! is_string($digits) || $digits === '') {
            return $destination;
        }

        if (str_starts_with($digits, '57')) {
            return $digits;
        }

        if (str_starts_with($digits, '3') && strlen($digits) === 10) {
            return '57'.$digits;
        }

        return $digits;
    }
}
