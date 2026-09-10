<?php

namespace Tests\Feature\Sms;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Enums\AuditEventType;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\OtpPurpose;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\SmsAttemptStatus;
use App\Enums\SmsPurpose;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\SmsAttempt;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsGatewayResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class RadicadoSmsRetryTest extends TestCase
{
    use RefreshPhaseDatabase;

    private int $nextRadicado = 7000;

    public function test_moto_retry_requires_permission_and_uses_current_holder_phone(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000001');
        $moto = $this->makeMoto($owner, $advisor);
        $this->createInitialAttempt(moto: $moto);
        $moto->forceFill(['holder_phone' => '+573009998888'])->save();
        $this->actingAsReady($advisor);

        $this->from(route('advisor.moto.edit', $moto))
            ->post(route('advisor.moto.sms.retry', $moto), ['_token' => 'phase07-token'])
            ->assertForbidden();

        $this->grantAdvisor(PermissionKey::RADICADO_SMS_RETRY);

        $this->from(route('advisor.moto.edit', $moto))
            ->post(route('advisor.moto.sms.retry', $moto), ['_token' => 'phase07-token'])
            ->assertRedirect(route('advisor.moto.edit', $moto, absolute: false));

        $attempt = SmsAttempt::query()
            ->where('moto_cancellation_id', $moto->id)
            ->where('is_manual_retry', true)
            ->firstOrFail();
        $audit = Audit::query()->where('event_type', AuditEventType::RADICADO_SMS_RETRY_REQUESTED)->firstOrFail();

        $this->assertSame(SmsAttemptStatus::SENT, $attempt->status);
        $this->assertSame('+573009998888', $attempt->destination);
        $this->assertNotNull($attempt->provider_reference);
        $this->assertNotNull($attempt->completed_at);
        $this->assertSame($advisor->id, $audit->actor_user_id);
        $this->assertSame($attempt->id, $audit->metadata['sms_attempt_id']);
        $this->assertSame('***********88', $audit->metadata['destination_snapshot']);
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::RADICADO_SMS_RETRY_REQUESTED)->count());
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_moto_retry_is_blocked_before_radicado_exists(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000099');
        $moto = $this->makeMoto($owner, $advisor);
        $moto->forceFill([
            'radicado' => null,
            'status' => CancellationStatus::PENDIENTE_RADICACION,
        ])->save();
        $this->grantAdvisor(PermissionKey::RADICADO_SMS_RETRY);
        $this->actingAsReady($advisor);

        $this->from(route('advisor.moto.edit', $moto))
            ->post(route('advisor.moto.sms.retry', $moto), ['_token' => 'phase07-token'])
            ->assertRedirect(route('advisor.moto.edit', $moto, absolute: false))
            ->assertSessionHasErrors(['sms_retry']);

        $this->assertSame(0, SmsAttempt::query()->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(0, Audit::query()->where('event_type', AuditEventType::RADICADO_SMS_RETRY_REQUESTED)->count());
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_credit_retry_failure_is_sanitized_and_does_not_rollback_cancellation(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000002');
        $credit = $this->makeCredit($owner, $advisor);
        $this->createInitialAttempt(credit: $credit);
        $this->grantAdvisor(PermissionKey::RADICADO_SMS_RETRY);
        $this->actingAsReady($advisor);

        $this->app->bind(SmsGateway::class, fn (): SmsGateway => new class implements SmsGateway
        {
            public function send(string $destination, string $message, SmsPurpose $purpose): SmsGatewayResult
            {
                throw new RuntimeException('secret-token=do-not-store');
            }
        });

        $this->from(route('advisor.credit.edit', $credit))
            ->post(route('advisor.credit.sms.retry', $credit), ['_token' => 'phase07-token'])
            ->assertRedirect(route('advisor.credit.edit', $credit, absolute: false));

        $credit->refresh();
        $attempt = SmsAttempt::query()
            ->where('credit_cancellation_id', $credit->id)
            ->where('is_manual_retry', true)
            ->firstOrFail();

        $this->assertTrue($credit->exists);
        $this->assertSame(SmsAttemptStatus::UNKNOWN, $attempt->status);
        $this->assertSame('PROVIDER_EXCEPTION', $attempt->safe_error_code);
        $this->assertSame('Provider error.', $attempt->safe_error_message);
        $this->assertStringNotContainsString('secret-token', (string) $attempt->safe_error_message);
        $this->assertNotNull($attempt->completed_at);
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::RADICADO_SMS_RETRY_REQUESTED)->where('credit_cancellation_id', $credit->id)->count());
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_retry_cooldown_and_rolling_limit_are_enforced(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000003');
        $moto = $this->makeMoto($owner, $advisor);
        $this->createInitialAttempt(moto: $moto);
        $this->grantAdvisor(PermissionKey::RADICADO_SMS_RETRY);
        $this->actingAsReady($advisor);

        $this->createManualRetry($moto, createdAt: now()->subMinute());

        $this->from(route('advisor.moto.edit', $moto))
            ->post(route('advisor.moto.sms.retry', $moto), ['_token' => 'phase07-token'])
            ->assertRedirect(route('advisor.moto.edit', $moto, absolute: false))
            ->assertSessionHasErrors(['sms_retry']);

        $this->assertSame(1, SmsAttempt::query()->where('is_manual_retry', true)->count());

        SmsAttempt::query()->delete();
        $this->createInitialAttempt(moto: $moto);
        $this->createManualRetry($moto, createdAt: now()->subMinutes(30));
        $this->createManualRetry($moto, createdAt: now()->subMinutes(20));
        $this->createManualRetry($moto, createdAt: now()->subMinutes(10));

        $this->from(route('advisor.moto.edit', $moto))
            ->post(route('advisor.moto.sms.retry', $moto), ['_token' => 'phase07-token'])
            ->assertRedirect(route('advisor.moto.edit', $moto, absolute: false))
            ->assertSessionHasErrors(['sms_retry']);

        $this->assertSame(3, SmsAttempt::query()->where('is_manual_retry', true)->count());
    }

    public function test_endpoint_limiter_rejects_more_than_ten_retries_per_minute(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000004');
        $moto = $this->makeMoto($owner, $advisor);
        $this->createInitialAttempt(moto: $moto);
        $this->grantAdvisor(PermissionKey::RADICADO_SMS_RETRY);
        $this->actingAsReady($advisor);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            RateLimiter::hit('radicado-sms-retry:user:'.$advisor->id, 60);
        }

        $this->from(route('advisor.moto.edit', $moto))
            ->post(route('advisor.moto.sms.retry', $moto), ['_token' => 'phase07-token'])
            ->assertRedirect(route('advisor.moto.edit', $moto, absolute: false))
            ->assertSessionHasErrors(['sms_retry']);

        $this->assertSame(0, SmsAttempt::query()->where('is_manual_retry', true)->count());
        $this->assertSame(0, Audit::query()->where('event_type', AuditEventType::RADICADO_SMS_RETRY_REQUESTED)->count());
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_retry_gateway_is_called_outside_database_transaction(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000005');
        $moto = $this->makeMoto($owner, $advisor);
        $this->createInitialAttempt(moto: $moto);
        $this->grantAdvisor(PermissionKey::RADICADO_SMS_RETRY);
        $this->actingAsReady($advisor);
        $observer = new class
        {
            /** @var list<int> */
            public array $transactionLevels = [];
        };

        $this->app->bind(SmsGateway::class, fn (): SmsGateway => new class($observer) implements SmsGateway
        {
            public function __construct(private readonly object $observer) {}

            public function send(string $destination, string $message, SmsPurpose $purpose): SmsGatewayResult
            {
                $this->observer->transactionLevels[] = DB::transactionLevel();

                return SmsGatewayResult::sent('outside-transaction-provider-ref');
            }
        });

        $this->from(route('advisor.moto.edit', $moto))
            ->post(route('advisor.moto.sms.retry', $moto), ['_token' => 'phase07-token'])
            ->assertRedirect(route('advisor.moto.edit', $moto, absolute: false));

        $attempt = SmsAttempt::query()
            ->where('moto_cancellation_id', $moto->id)
            ->where('is_manual_retry', true)
            ->firstOrFail();

        $this->assertSame([0], $observer->transactionLevels);
        $this->assertSame(SmsAttemptStatus::SENT, $attempt->status);
        $this->assertSame('outside-transaction-provider-ref', $attempt->provider_reference);
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::RADICADO_SMS_RETRY_REQUESTED)->count());
        $this->assertSame(0, Activity::query()->count());
    }

    private function createInitialAttempt(?MotoCancellation $moto = null, ?CreditCancellation $credit = null): SmsAttempt
    {
        return SmsAttempt::query()->create([
            'moto_cancellation_id' => $moto?->id,
            'credit_cancellation_id' => $credit?->id,
            'purpose' => SmsPurpose::RADICADO,
            'status' => SmsAttemptStatus::SENT,
            'is_manual_retry' => false,
            'destination' => $moto?->holder_phone ?? $credit?->holder_phone ?? '+573001234567',
            'completed_at' => now(),
        ]);
    }

    private function createManualRetry(MotoCancellation $moto, mixed $createdAt): SmsAttempt
    {
        $attempt = SmsAttempt::query()->create([
            'moto_cancellation_id' => $moto->id,
            'purpose' => SmsPurpose::RADICADO,
            'status' => SmsAttemptStatus::FAILED,
            'is_manual_retry' => true,
            'destination' => $moto->holder_phone,
            'completed_at' => $createdAt,
        ]);
        $attempt->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $attempt;
    }

    private function grantAdvisor(PermissionKey $permissionKey): void
    {
        app(GrantRolePermissionAction::class)->execute(
            Role::query()->where('code', RoleCode::ADVISOR->value)->firstOrFail(),
            $permissionKey,
        );
    }

    private function makeAdvisor(): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::ADVISOR),
            'username' => 'ADVISOR'.bin2hex(random_bytes(3)),
            'identity' => null,
            'name' => 'Advisor User',
            'email' => 'advisor@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function makeClient(string $identity): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => $identity,
            'identity' => $identity,
            'name' => 'Client '.$identity,
            'email' => $identity.'@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function makeMoto(User $owner, User $creator): MotoCancellation
    {
        return MotoCancellation::query()->create([
            'otp_challenge_id' => $this->makeOtpChallenge(OtpPurpose::CREATE_MOTO)->id,
            'radicado' => $this->nextRadicado++,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
            'assigned_advisor_user_id' => $creator->id,
            'origin' => CancellationOrigin::ADVISOR,
            'status' => CancellationStatus::EN_GESTION,
            'version' => 1,
            'holder_name' => $owner->name,
            'holder_cedula' => (string) $owner->identity,
            'property_lien_adeinco' => true,
            'plate' => 'ABC123',
            'holder_phone' => (string) $owner->phone,
            'holder_email' => (string) $owner->email,
            'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS,
            'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL,
            'is_credit_holder' => false,
            'credit_owner_name' => 'Credit Owner',
            'credit_owner_cedula' => '9876543210',
            'ownership_declaration_accepted' => true,
            'data_processing_accepted' => true,
        ]);
    }

    private function makeCredit(User $owner, User $creator): CreditCancellation
    {
        return CreditCancellation::query()->create([
            'otp_challenge_id' => $this->makeOtpChallenge(OtpPurpose::CREATE_CREDIT)->id,
            'radicado' => $this->nextRadicado++,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
            'assigned_advisor_user_id' => $creator->id,
            'origin' => CancellationOrigin::ADVISOR,
            'status' => CancellationStatus::EN_GESTION,
            'version' => 1,
            'holder_name' => $owner->name,
            'holder_cedula' => (string) $owner->identity,
            'credit_number' => '00012345',
            'holder_phone' => (string) $owner->phone,
            'holder_email' => (string) $owner->email,
            'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS,
            'cancel_personal_accidents' => true,
            'cancel_unemployment_insurance' => false,
            'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL,
            'credit_holder_declaration_accepted' => true,
            'data_processing_accepted' => true,
        ]);
    }

    private function makeOtpChallenge(OtpPurpose $purpose): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase07-'.bin2hex(random_bytes(8)),
            'purpose' => $purpose,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase07'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }

    private function actingAsReady(User $user): void
    {
        RateLimiter::clear('radicado-sms-retry:user:'.$user->id);
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
                '_token' => 'phase07-token',
            ]);
    }
}
