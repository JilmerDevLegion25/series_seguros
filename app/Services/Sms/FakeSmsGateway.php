<?php

namespace App\Services\Sms;

use App\Enums\SmsPurpose;
use App\Services\Clock\Clock;
use Illuminate\Support\Str;

final class FakeSmsGateway implements SmsGateway
{
    /**
     * @var list<array{destination: string, message: string, purpose: SmsPurpose, provider_reference: string}>
     */
    private static array $sentMessages = [];

    private static ?SmsGatewayResult $nextResult = null;

    public function __construct(
        private Clock $clock,
    ) {}

    public function send(string $destination, string $message, SmsPurpose $purpose): SmsGatewayResult
    {
        $providerReference = 'fake-'.$this->clock->now()->format('YmdHis').'-'.Str::random(8);

        self::$sentMessages[] = [
            'destination' => $destination,
            'message' => $message,
            'purpose' => $purpose,
            'provider_reference' => $providerReference,
        ];

        if (self::$nextResult instanceof SmsGatewayResult) {
            $result = self::$nextResult;
            self::$nextResult = null;

            return $result;
        }

        return SmsGatewayResult::sent(providerReference: $providerReference);
    }

    /**
     * @return list<array{destination: string, message: string, purpose: SmsPurpose, provider_reference: string}>
     */
    public static function sentMessages(): array
    {
        return self::$sentMessages;
    }

    public static function clearSentMessages(): void
    {
        self::$sentMessages = [];
        self::$nextResult = null;
    }

    public static function fakeNextResult(SmsGatewayResult $result): void
    {
        self::$nextResult = $result;
    }
}
