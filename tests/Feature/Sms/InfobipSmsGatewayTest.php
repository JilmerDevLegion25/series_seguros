<?php

namespace Tests\Feature\Sms;

use App\Enums\SmsAttemptStatus;
use App\Enums\SmsPurpose;
use App\Services\Sms\InfobipSmsGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class InfobipSmsGatewayTest extends TestCase
{
    public function test_it_sends_infobip_payload_and_maps_successful_response(): void
    {
        Http::fake([
            'https://sms.example.test/send' => Http::response([
                'messages' => [
                    [
                        'messageId' => 'infobip-message-1',
                        'status' => ['groupName' => 'PENDING'],
                    ],
                ],
            ]),
        ]);

        $result = $this->gateway()->send(
            destination: '+573001234567',
            message: '123456 es su codigo de validacion Corredores de Series Seguros',
            purpose: SmsPurpose::OTP,
        );

        $this->assertSame(SmsAttemptStatus::SENT, $result->status);
        $this->assertSame('infobip-message-1', $result->providerReference);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sms.example.test/send'
            && $request->hasHeader('Authorization', 'Basic testing-token')
            && $request['messages'][0]['destinations'][0]['to'] === '573001234567'
            && $request['messages'][0]['from'] === 'InfoSMS'
            && $request['messages'][0]['text'] === '123456 es su codigo de validacion Corredores de Series Seguros');
    }

    public function test_it_prefixes_colombian_national_destination_for_infobip(): void
    {
        Http::fake([
            'https://sms.example.test/send' => Http::response(['messages' => []]),
        ]);

        $this->gateway()->send('3001234567', 'mensaje', SmsPurpose::RADICADO);

        Http::assertSent(fn ($request): bool => $request['messages'][0]['destinations'][0]['to'] === '573001234567');
    }

    public function test_it_returns_failed_result_for_http_rejection(): void
    {
        Http::fake([
            'https://sms.example.test/send' => Http::response(['requestError' => ['serviceException' => []]], 401),
        ]);

        $result = $this->gateway()->send('+573001234567', 'mensaje', SmsPurpose::OTP);

        $this->assertSame(SmsAttemptStatus::FAILED, $result->status);
        $this->assertSame('PROVIDER_HTTP_401', $result->safeErrorCode);
        $this->assertSame('Provider rejected request.', $result->safeErrorMessage);
    }

    public function test_it_returns_failed_result_for_rejected_provider_status(): void
    {
        Http::fake([
            'https://sms.example.test/send' => Http::response([
                'messages' => [
                    [
                        'messageId' => 'rejected-message',
                        'status' => ['groupName' => 'REJECTED'],
                    ],
                ],
            ]),
        ]);

        $result = $this->gateway()->send('+573001234567', 'mensaje', SmsPurpose::OTP);

        $this->assertSame(SmsAttemptStatus::FAILED, $result->status);
        $this->assertSame('PROVIDER_REJECTED', $result->safeErrorCode);
        $this->assertSame('Provider rejected request.', $result->safeErrorMessage);
    }

    public function test_it_returns_unknown_for_provider_connection_errors(): void
    {
        Http::fake([
            'https://sms.example.test/send' => fn (): never => throw new ConnectionException('Connection timed out with sensitive provider details.'),
        ]);

        $result = $this->gateway()->send('+51989846262', 'mensaje', SmsPurpose::OTP);

        $this->assertSame(SmsAttemptStatus::UNKNOWN, $result->status);
        $this->assertSame('PROVIDER_CONNECTION_ERROR', $result->safeErrorCode);
        $this->assertSame('Provider response unavailable.', $result->safeErrorMessage);
    }

    private function gateway(): InfobipSmsGateway
    {
        return new InfobipSmsGateway(
            endpoint: 'https://sms.example.test/send',
            authorizationHeader: 'Basic testing-token',
            from: 'InfoSMS',
            connectTimeoutSeconds: 3,
            totalTimeoutSeconds: 10,
        );
    }
}
