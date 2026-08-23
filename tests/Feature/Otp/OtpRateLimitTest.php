<?php

namespace Tests\Feature\Otp;

use App\Actions\Otp\StartPasswordRecoveryAction;
use App\Actions\Otp\StartPublicOtpChallengeAction;
use App\Actions\Otp\VerifyOtpChallengeAction;
use App\DTOs\Otp\StartPasswordRecoveryData;
use App\DTOs\Otp\StartPublicOtpChallengeData;
use App\Enums\OtpPurpose;
use App\Exceptions\OtpChallengeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class OtpRateLimitTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_public_init_phone_and_identity_limits(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            app(StartPublicOtpChallengeAction::class)->execute(new StartPublicOtpChallengeData(
                purpose: OtpPurpose::CREATE_MOTO,
                identity: '1234567890',
                phone: '3001234567',
                payload: ['attempt' => $attempt],
                ip: '127.10.0.'.$attempt,
            ));
        }

        $this->expectException(TooManyRequestsHttpException::class);
        app(StartPublicOtpChallengeAction::class)->execute(new StartPublicOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            identity: '1234567890',
            phone: '3001234567',
            payload: ['attempt' => 6],
            ip: '127.10.0.6',
        ));
    }

    public function test_public_init_ip_limit(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            app(StartPublicOtpChallengeAction::class)->execute(new StartPublicOtpChallengeData(
                purpose: OtpPurpose::CREATE_CREDIT,
                identity: (string) (9000000000 + $attempt),
                phone: '300'.str_pad((string) $attempt, 7, '0', STR_PAD_LEFT),
                payload: ['attempt' => $attempt],
                ip: '127.20.0.1',
            ));
        }

        $this->expectException(TooManyRequestsHttpException::class);
        app(StartPublicOtpChallengeAction::class)->execute(new StartPublicOtpChallengeData(
            purpose: OtpPurpose::CREATE_CREDIT,
            identity: '9000000021',
            phone: '3000000021',
            payload: ['attempt' => 21],
            ip: '127.20.0.1',
        ));
    }

    public function test_recovery_target_and_ip_limits(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            app(StartPasswordRecoveryAction::class)->execute(new StartPasswordRecoveryData(
                username: 'missing-target',
                ip: '127.30.0.'.$attempt,
            ));
        }

        $this->expectException(TooManyRequestsHttpException::class);
        app(StartPasswordRecoveryAction::class)->execute(new StartPasswordRecoveryData(
            username: 'missing-target',
            ip: '127.30.0.4',
        ));
    }

    public function test_recovery_ip_limit(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            app(StartPasswordRecoveryAction::class)->execute(new StartPasswordRecoveryData(
                username: 'missing'.$attempt,
                ip: '127.40.0.1',
            ));
        }

        $this->expectException(TooManyRequestsHttpException::class);
        app(StartPasswordRecoveryAction::class)->execute(new StartPasswordRecoveryData(
            username: 'missing-final',
            ip: '127.40.0.1',
        ));
    }

    public function test_technical_otp_limiter(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            try {
                app(VerifyOtpChallengeAction::class)->execute(str_repeat('f', 64), '000000', '127.50.0.1');
            } catch (OtpChallengeException) {
                //
            }
        }

        $this->expectException(TooManyRequestsHttpException::class);
        app(VerifyOtpChallengeAction::class)->execute(str_repeat('f', 64), '000000', '127.50.0.1');
    }
}
