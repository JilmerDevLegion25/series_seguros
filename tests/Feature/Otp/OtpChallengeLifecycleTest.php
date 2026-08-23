<?php

namespace Tests\Feature\Otp;

use App\Actions\Otp\DecryptOtpPayloadAction;
use App\Actions\Otp\InvalidateOtpChallengeAction;
use App\Actions\Otp\IssueOtpChallengeAction;
use App\Actions\Otp\ResendOtpChallengeAction;
use App\Actions\Otp\StartPublicOtpChallengeAction;
use App\Actions\Otp\VerifyAndConsumeOtpChallengeAction;
use App\Actions\Otp\VerifyOtpChallengeAction;
use App\DTOs\Otp\IssueOtpChallengeData;
use App\DTOs\Otp\StartPublicOtpChallengeData;
use App\Enums\OtpPurpose;
use App\Exceptions\OtpChallengeException;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Services\Clock\Clock;
use App\Services\Sms\FakeSmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class OtpChallengeLifecycleTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_issue_persists_mac_not_plaintext_and_encrypted_payload(): void
    {
        $clock = new MutableOtpClock(CarbonImmutable::parse('2026-08-20 10:00:00'));
        $this->app->instance(Clock::class, $clock);

        $result = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+573001234567',
            payload: ['identity' => '1234567890', 'plate' => 'ABC123'],
        ));

        $challenge = $result->challenge->fresh();
        $otp = $this->latestOtp();

        $this->assertSame(64, strlen($challenge->public_reference));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $challenge->public_reference);
        $this->assertNotSame((string) $challenge->id, $challenge->public_reference);
        $this->assertSame(OtpPurpose::CREATE_MOTO, $challenge->purpose);
        $this->assertSame('+573001234567', $challenge->destination_snapshot);
        $this->assertSame(1, $challenge->emission_count);
        $this->assertSame(0, $challenge->failed_attempts);
        $this->assertSame($clock->now()->addMinutes(5)->timestamp, $challenge->expires_at->timestamp);
        $this->assertNotSame($otp, $challenge->otp_mac);
        $this->assertStringNotContainsString($otp, implode('|', $challenge->getAttributes()));
        $this->assertStringNotContainsString('ABC123', (string) $challenge->encrypted_payload);
        $this->assertSame(['identity' => '1234567890', 'plate' => 'ABC123'], app(DecryptOtpPayloadAction::class)->execute($challenge));
    }

    public function test_public_reference_is_unique_and_unpredictable(): void
    {
        $first = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+573001234567',
        ))->challenge;
        $second = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_CREDIT,
            destination: '+573009998888',
        ))->challenge;

        $this->assertNotSame($first->public_reference, $second->public_reference);
        $this->assertSame(2, OtpChallenge::query()->distinct('public_reference')->count('public_reference'));
    }

    public function test_database_enforces_purpose_allowlist_and_terminal_xor(): void
    {
        $this->expectException(QueryException::class);

        DB::table('otp_challenges')->insert([
            'public_reference' => str_repeat('a', 64),
            'purpose' => 'FORBIDDEN',
            'destination_snapshot' => '+573001234567',
            'otp_mac' => str_repeat('b', 64),
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function test_consumed_and_invalidated_cannot_coexist(): void
    {
        $challenge = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+573001234567',
        ))->challenge;

        $this->expectException(QueryException::class);

        $challenge->forceFill([
            'consumed_at' => now(),
            'invalidated_at' => now(),
        ])->save();
    }

    public function test_resend_cooldown_limit_and_new_otp_invalidates_previous(): void
    {
        $clock = new MutableOtpClock(CarbonImmutable::parse('2026-08-20 10:00:00'));
        $this->app->instance(Clock::class, $clock);
        $challenge = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+573001234567',
        ))->challenge;
        $firstOtp = $this->latestOtp();

        $this->expectException(OtpChallengeException::class);
        try {
            app(ResendOtpChallengeAction::class)->execute($challenge->public_reference, '127.0.0.1');
        } finally {
            $clock->setNow(CarbonImmutable::parse('2026-08-20 10:01:01'));
            $resend = app(ResendOtpChallengeAction::class)->execute($challenge->public_reference, '127.0.0.2')->challenge;
            $secondOtp = $this->latestOtp();

            $this->assertSame(2, $resend->emission_count);
            $this->assertNotSame($firstOtp, $secondOtp);
            $this->assertFalse(app(VerifyOtpChallengeAction::class)->execute($challenge->public_reference, $firstOtp, '127.0.0.3')->valid);
            $this->assertTrue(app(VerifyOtpChallengeAction::class)->execute($challenge->public_reference, $secondOtp, '127.0.0.4')->valid);
        }
    }

    public function test_resend_stops_at_three_total_emissions(): void
    {
        $clock = new MutableOtpClock(CarbonImmutable::parse('2026-08-20 10:00:00'));
        $this->app->instance(Clock::class, $clock);
        $challenge = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+573001234567',
        ))->challenge;

        $clock->setNow(CarbonImmutable::parse('2026-08-20 10:01:01'));
        app(ResendOtpChallengeAction::class)->execute($challenge->public_reference, '127.0.0.1');
        $clock->setNow(CarbonImmutable::parse('2026-08-20 10:02:02'));
        app(ResendOtpChallengeAction::class)->execute($challenge->public_reference, '127.0.0.2');
        $clock->setNow(CarbonImmutable::parse('2026-08-20 10:03:03'));

        $this->expectException(OtpChallengeException::class);
        app(ResendOtpChallengeAction::class)->execute($challenge->public_reference, '127.0.0.3');
    }

    public function test_invalid_attempts_increment_and_fifth_failure_invalidates(): void
    {
        $challenge = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+573001234567',
            payload: ['identity' => '1234567890'],
        ))->challenge;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = app(VerifyOtpChallengeAction::class)->execute($challenge->public_reference, '000000', '127.0.1.'.$attempt);
            $this->assertFalse($result->valid);
        }

        $challenge->refresh();

        $this->assertSame(5, $challenge->failed_attempts);
        $this->assertTrue($challenge->isInvalidated());
        $this->assertSame('MAX_FAILURES', $challenge->invalidation_reason);
        $this->assertNull($challenge->encrypted_payload);
    }

    public function test_expired_consumed_and_invalidated_challenges_are_denied(): void
    {
        $clock = new MutableOtpClock(CarbonImmutable::parse('2026-08-20 10:00:00'));
        $this->app->instance(Clock::class, $clock);

        $expired = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+573001234567',
        ))->challenge;
        $expiredOtp = $this->latestOtp();
        $clock->setNow(CarbonImmutable::parse('2026-08-20 10:05:00'));
        $this->assertFalse(app(VerifyOtpChallengeAction::class)->execute($expired->public_reference, $expiredOtp, '127.0.0.10')->valid);

        $clock->setNow(CarbonImmutable::parse('2026-08-20 11:00:00'));
        $consumed = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_CREDIT,
            destination: '+573001111111',
        ))->challenge;
        $consumedOtp = $this->latestOtp();
        $this->assertTrue(app(VerifyAndConsumeOtpChallengeAction::class)->execute($consumed->public_reference, $consumedOtp, '127.0.0.11')->valid);
        $this->assertFalse(app(VerifyOtpChallengeAction::class)->execute($consumed->public_reference, $consumedOtp, '127.0.0.12')->valid);

        $invalidated = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_CREDIT,
            destination: '+573002222222',
        ))->challenge;
        $invalidatedOtp = $this->latestOtp();
        app(InvalidateOtpChallengeAction::class)->execute($invalidated->public_reference, 'MANUAL');
        $this->assertFalse(app(VerifyOtpChallengeAction::class)->execute($invalidated->public_reference, $invalidatedOtp, '127.0.0.13')->valid);
    }

    public function test_verify_request_cannot_replace_payload_and_terminal_minimizes_payload(): void
    {
        $result = app(StartPublicOtpChallengeAction::class)->execute(new StartPublicOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            identity: '1.234.567.890',
            phone: '3001234567',
            payload: ['draft' => 'original'],
            ip: '127.0.0.1',
        ));
        $challenge = $result->challenge;
        $otp = $this->latestOtp();

        $this->post(route('otp.verify', $challenge->public_reference), [
            'otp' => $otp,
            'payload' => ['draft' => 'tampered'],
        ])->assertNoContent();

        $payload = app(DecryptOtpPayloadAction::class)->execute($challenge->fresh());
        $this->assertSame('original', $payload['draft']);
        $this->assertSame('1234567890', $payload['identity']);
        $this->assertSame('+573001234567', $payload['phone']);

        app(VerifyAndConsumeOtpChallengeAction::class)->execute($challenge->public_reference, $otp, '127.0.0.2');
        $this->assertNull($challenge->fresh()->encrypted_payload);
    }

    public function test_public_create_foundation_does_not_create_business_aggregates_or_users(): void
    {
        app(StartPublicOtpChallengeAction::class)->execute(new StartPublicOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            identity: '1234567890',
            phone: '3001234567',
            payload: ['canonical' => true],
            ip: '127.0.0.1',
        ));
        app(StartPublicOtpChallengeAction::class)->execute(new StartPublicOtpChallengeData(
            purpose: OtpPurpose::CREATE_CREDIT,
            identity: '9876543210',
            phone: '3009998888',
            payload: ['canonical' => true],
            ip: '127.0.0.2',
        ));

        $this->assertSame(2, OtpChallenge::query()->count());
        $this->assertTrue(Schema::hasTable('moto_cancellations'));
        $this->assertSame(0, MotoCancellation::query()->count());
        $this->assertTrue(Schema::hasTable('credit_cancellations'));
        $this->assertSame(0, CreditCancellation::query()->count());
        $this->assertSame(0, DB::table('users')->count());
    }

    private function latestOtp(): string
    {
        $messages = FakeSmsGateway::sentMessages();
        $message = end($messages);

        $this->assertIsArray($message);
        $this->assertSame(1, preg_match('/\b(\d{6})\b/', $message['message'], $matches));

        return $matches[1];
    }
}

final class MutableOtpClock implements Clock
{
    public function __construct(
        private CarbonImmutable $now,
    ) {}

    public function now(): CarbonImmutable
    {
        return $this->now;
    }

    public function setNow(CarbonImmutable $now): void
    {
        $this->now = $now;
    }
}
