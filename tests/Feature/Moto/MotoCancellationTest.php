<?php

namespace Tests\Feature\Moto;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Actions\Otp\IssueOtpChallengeAction;
use App\Actions\Otp\VerifyAndConsumeOtpChallengeAction;
use App\DTOs\Otp\IssueOtpChallengeData;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
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
use App\Models\RadicadoSequence;
use App\Models\Role;
use App\Models\SmsAttempt;
use App\Models\User;
use App\Services\Clock\Clock;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsGatewayResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class MotoCancellationTest extends TestCase
{
    use RefreshPhaseDatabase {
        setUp as refreshPhaseDatabaseSetUp;
    }

    protected function setUp(): void
    {
        $this->refreshPhaseDatabaseSetUp();

        config()->set('sms.driver', 'fake');
        $this->app->bind(SmsGateway::class, fn (): SmsGateway => new FakeSmsGateway(app(Clock::class)));
        $this->withSession(['_token' => 'phase04-token']);
    }

    public function test_public_moto_e2e_creates_only_after_valid_otp(): void
    {
        $this->seedMotoSequence(486);

        $this->post(route('public.moto.store'), $this->validPayload([
            'plate' => ' abc123 ',
            'holder_email' => 'CLIENT@EXAMPLE.TEST',
        ]))->assertOk();

        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();

        $this->assertSame(0, MotoCancellation::query()->count());
        $this->assertSame(0, User::query()->count());

        $this->post(route('public.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
            'owner_user_id' => 999,
            'created_by_user_id' => 999,
            'status' => CancellationStatus::RESPUESTA_OBTENIDA->value,
            'radicado' => 999,
            'version' => 99,
            'payload' => ['plate' => 'TAMPERED'],
        ])->assertOk()
            ->assertSee('Solicitud recibida')
            ->assertSee('Radicado Moto')
            ->assertSee('486')
            ->assertSee('Su solicitud ha sido enviada con exito')
            ->assertSee('portal.correovalle.com/progreser');

        $moto = MotoCancellation::query()->firstOrFail();
        $client = User::query()->firstOrFail();

        $this->assertSame(486, $moto->radicado);
        $this->assertSame($client->id, $moto->owner_user_id);
        $this->assertSame($client->id, $moto->created_by_user_id);
        $this->assertSame(CancellationOrigin::PUBLIC, $moto->origin);
        $this->assertSame(CancellationStatus::EN_GESTION, $moto->status);
        $this->assertSame(1, $moto->version);
        $this->assertSame('ABC123', $moto->plate);
        $this->assertSame('client@example.test', $moto->holder_email);
        $this->assertSame('1234567890', $client->identity);
        $this->assertSame('+573001234567', $client->phone);
        $this->assertTrue($client->must_change_password);
        $this->assertTrue(Hash::check((string) config('authentication.client_initial_password'), $client->password));
        $this->assertTrue($challenge->fresh()->isConsumed());
        $this->assertNull($challenge->fresh()->encrypted_payload);
        $this->assertGuest();

        $this->assertSame(1, Activity::query()->where('type', ActivityType::CREATED)->count());
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::CANCELLATION_CREATED)->count());
        $this->assertSame(1, SmsAttempt::query()->where('purpose', SmsPurpose::RADICADO)->where('status', SmsAttemptStatus::SENT)->count());
        $this->assertStringContainsString('486', $this->lastSmsMessage());
    }

    public function test_validation_rejects_conditional_credit_owner_fields(): void
    {
        $this->seedMotoSequence(1);

        $this->from(route('public.moto.create'))
            ->post(route('public.moto.store'), $this->validPayload([
                'is_credit_holder' => '0',
                'credit_owner_name' => '',
                'credit_owner_cedula' => '',
            ]))
            ->assertRedirect(route('public.moto.create', absolute: false))
            ->assertSessionHasErrors(['credit_owner_name', 'credit_owner_cedula']);

        $this->assertSame(0, OtpChallenge::query()->count());
        $this->assertSame(0, MotoCancellation::query()->count());
    }

    public function test_invalid_expired_or_consumed_otp_creates_nothing(): void
    {
        $this->seedMotoSequence(100);

        $this->post(route('public.moto.store'), $this->validPayload(['holder_cedula' => '1111111111', 'holder_phone' => '3001111111']))->assertOk();
        $invalid = OtpChallenge::query()->latest('id')->firstOrFail();
        $invalidOtp = $this->latestOtp();
        $this->post(route('public.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $invalid->public_reference,
            'otp' => $this->wrongOtp($invalidOtp),
        ])->assertSessionHasErrors(['otp']);

        $this->post(route('public.moto.store'), $this->validPayload(['holder_cedula' => '2222222222', 'holder_phone' => '3002222222']))->assertOk();
        $expired = OtpChallenge::query()->latest('id')->firstOrFail();
        $expiredOtp = $this->latestOtp();
        $expired->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->post(route('public.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $expired->public_reference,
            'otp' => $expiredOtp,
        ])->assertSessionHasErrors(['otp']);

        $consumed = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+573003333333',
            payload: $this->payloadForChallenge($this->validPayload([
                'holder_cedula' => '3333333333',
                'holder_phone' => '3003333333',
            ])),
        ))->challenge;
        $consumedOtp = $this->latestOtp();
        app(VerifyAndConsumeOtpChallengeAction::class)->execute($consumed->public_reference, $consumedOtp, '127.0.0.20');

        $this->post(route('public.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $consumed->public_reference,
            'otp' => $consumedOtp,
        ])->assertSessionHasErrors(['otp']);

        $this->assertSame(0, MotoCancellation::query()->count());
    }

    public function test_payload_cannot_be_replaced_and_existing_client_is_refreshed_without_password_reset(): void
    {
        $this->seedMotoSequence(200);
        $client = $this->makeClient([
            'identity' => '1234567890',
            'username' => '1234567890',
            'name' => 'Old Name',
            'email' => 'old@example.test',
            'phone' => '+573009998888',
            'password' => Hash::make('original-password'),
            'must_change_password' => false,
        ]);
        $originalPassword = $client->password;

        $this->post(route('public.moto.store'), $this->validPayload([
            'holder_name' => 'New Client Name',
            'holder_email' => 'new@example.test',
            'holder_phone' => '3001234567',
            'plate' => 'MOTO42',
        ]))->assertOk();

        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();

        $this->post(route('public.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
            'holder_name' => 'Browser Tamper',
            'plate' => 'BAD999',
        ])->assertOk();

        $client->refresh();
        $moto = MotoCancellation::query()->firstOrFail();

        $this->assertSame('New Client Name', $client->name);
        $this->assertSame('new@example.test', $client->email);
        $this->assertSame('+573001234567', $client->phone);
        $this->assertSame($originalPassword, $client->password);
        $this->assertFalse($client->must_change_password);
        $this->assertSame('MOTO42', $moto->plate);
        $this->assertSame('New Client Name', $moto->holder_name);
    }

    public function test_sms_failure_does_not_rollback_moto_creation(): void
    {
        $this->seedMotoSequence(300);

        $this->post(route('public.moto.store'), $this->validPayload())->assertOk();
        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();

        FakeSmsGateway::fakeNextResult(SmsGatewayResult::failed('PROVIDER_DOWN', 'Provider unavailable'));

        $this->post(route('public.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
        ])->assertOk();

        $this->assertSame(1, MotoCancellation::query()->count());
        $attempt = SmsAttempt::query()->firstOrFail();
        $this->assertSame(SmsAttemptStatus::FAILED, $attempt->status);
        $this->assertSame('PROVIDER_DOWN', $attempt->safe_error_code);
    }

    public function test_lien_moto_after_valid_otp_is_pending_without_radicado_or_radicado_sms(): void
    {
        $this->seedMotoSequence(350);

        $this->post(route('public.moto.store'), $this->validPayload([
            'property_lien_adeinco' => '1',
        ]))->assertOk();

        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();
        $this->assertCount(1, FakeSmsGateway::sentMessages());

        $this->post(route('public.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
        ])->assertOk()
            ->assertSee('Pendiente de radicacion')
            ->assertSee('validación administrativa previa')
            ->assertSee('Copia de la tarjeta de propiedad del vehículo')
            ->assertDontSee('Radicado Moto')
            ->assertDontSee('350');

        $moto = MotoCancellation::query()->firstOrFail();

        $this->assertNull($moto->radicado);
        $this->assertSame(CancellationStatus::PENDIENTE_RADICACION, $moto->status);
        $this->assertSame(1, $moto->version);
        $this->assertSame(350, RadicadoSequence::query()->where('type', CancellationType::MOTO)->value('next_value'));
        $this->assertSame(0, SmsAttempt::query()->where('purpose', SmsPurpose::RADICADO)->count());
        $this->assertCount(1, FakeSmsGateway::sentMessages());
        $this->assertSame(1, Activity::query()->where('type', ActivityType::CREATED)->count());
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::CANCELLATION_CREATED)->count());
    }

    public function test_advisor_requires_permission_and_still_needs_otp(): void
    {
        $this->seedMotoSequence(400);
        $advisor = $this->makeAdvisor();

        $this->actingAsReady($advisor);
        $this->post(route('advisor.moto.store'), $this->validPayload())
            ->assertForbidden();

        $this->grantAdvisor(PermissionKey::CANCELLATIONS_CREATE);
        $this->post(route('advisor.moto.store'), $this->validPayload([
            'holder_cedula' => '4444444444',
            'holder_phone' => '3004444444',
        ]))->assertOk();

        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();
        $this->assertSame(0, MotoCancellation::query()->count());

        $this->post(route('advisor.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $challenge->public_reference,
            'otp' => $this->wrongOtp($otp),
        ])->assertSessionHasErrors(['otp']);
        $this->assertSame(0, MotoCancellation::query()->count());

        $this->post(route('advisor.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
        ])->assertOk();

        $moto = MotoCancellation::query()->firstOrFail();

        $this->assertSame(CancellationOrigin::ADVISOR, $moto->origin);
        $this->assertSame($advisor->id, $moto->created_by_user_id);
        $this->assertNotSame($advisor->id, $moto->owner_user_id);
    }

    public function test_advisor_generates_radicado_for_pending_lien_moto_and_sends_sms_outside_transaction(): void
    {
        $this->seedMotoSequence(800);
        $advisor = $this->makeAdvisor();
        $client = $this->makeClient([
            'identity' => '5555555555',
            'username' => '5555555555',
        ]);
        $moto = $this->makePendingLienMoto($client, $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);
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

                return SmsGatewayResult::sent('manual-radicado-ref');
            }
        });

        $this->from(route('advisor.cancellations.index'))
            ->post(route('advisor.moto.radicado.generate', $moto), [
                '_token' => 'phase04-token',
            ])
            ->assertRedirect(route('advisor.moto.edit', $moto, absolute: false));

        $moto->refresh();
        $attempt = SmsAttempt::query()->where('moto_cancellation_id', $moto->id)->firstOrFail();

        $this->assertSame(800, $moto->radicado);
        $this->assertSame(CancellationStatus::EN_GESTION, $moto->status);
        $this->assertSame(2, $moto->version);
        $this->assertSame(801, RadicadoSequence::query()->where('type', CancellationType::MOTO)->value('next_value'));
        $this->assertSame(SmsAttemptStatus::SENT, $attempt->status);
        $this->assertSame('manual-radicado-ref', $attempt->provider_reference);
        $this->assertSame([0], $observer->transactionLevels);
        $this->assertSame(1, Activity::query()->where('type', ActivityType::RADICADO_GENERATED)->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::RADICADO_GENERATED)->where('moto_cancellation_id', $moto->id)->count());
    }

    public function test_manual_radicado_sms_failure_does_not_rollback_radicacion(): void
    {
        $this->seedMotoSequence(850);
        $advisor = $this->makeAdvisor();
        $client = $this->makeClient([
            'identity' => '6666666666',
            'username' => '6666666666',
        ]);
        $moto = $this->makePendingLienMoto($client, $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);
        $this->actingAsReady($advisor);

        FakeSmsGateway::fakeNextResult(SmsGatewayResult::failed('PROVIDER_DOWN', 'Provider unavailable'));

        $this->post(route('advisor.moto.radicado.generate', $moto), [
            '_token' => 'phase04-token',
        ])
            ->assertRedirect(route('advisor.moto.edit', $moto, absolute: false));

        $moto->refresh();
        $attempt = SmsAttempt::query()->where('moto_cancellation_id', $moto->id)->firstOrFail();

        $this->assertSame(850, $moto->radicado);
        $this->assertSame(CancellationStatus::EN_GESTION, $moto->status);
        $this->assertSame(2, $moto->version);
        $this->assertSame(SmsAttemptStatus::FAILED, $attempt->status);
        $this->assertSame('PROVIDER_DOWN', $attempt->safe_error_code);
    }

    public function test_double_completion_is_idempotent_and_does_not_duplicate_sms(): void
    {
        $this->seedMotoSequence(500);

        $this->post(route('public.moto.store'), $this->validPayload())->assertOk();
        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();

        $this->post(route('public.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
        ])->assertOk();

        $this->post(route('public.moto.complete'), [
            '_token' => 'phase04-token',
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
        ])->assertOk()->assertSee('ya habia sido completada');

        $this->assertSame(1, MotoCancellation::query()->count());
        $this->assertSame(0, CreditCancellation::query()->count());
        $this->assertSame(1, SmsAttempt::query()->where('purpose', SmsPurpose::RADICADO)->count());
        $this->assertSame(501, RadicadoSequence::query()->where('type', CancellationType::MOTO)->value('next_value'));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'holder_name' => 'Client Moto',
            '_token' => 'phase04-token',
            'holder_cedula' => '1234567890',
            'property_lien_adeinco' => '0',
            'plate' => 'ABC123',
            'holder_phone' => '3001234567',
            'holder_email' => 'client@example.test',
            'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS->value,
            'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL->value,
            'is_credit_holder' => '0',
            'credit_owner_name' => 'Credit Owner',
            'credit_owner_cedula' => '9876543210',
            'ownership_declaration_accepted' => '1',
            'data_processing_accepted' => '1',
        ], $overrides);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, bool|int|string|null>
     */
    private function payloadForChallenge(array $payload): array
    {
        return [
            'payload_schema' => 'moto_create_v1',
            'origin' => CancellationOrigin::PUBLIC->value,
            'created_by_user_id' => null,
            'holder_name' => $payload['holder_name'],
            'holder_cedula' => $payload['holder_cedula'],
            'property_lien_adeinco' => $payload['property_lien_adeinco'] === '1',
            'plate' => $payload['plate'],
            'holder_phone' => '+57'.$payload['holder_phone'],
            'holder_email' => $payload['holder_email'],
            'cancellation_reason' => $payload['cancellation_reason'],
            'cancellation_information_source' => $payload['cancellation_information_source'],
            'is_credit_holder' => $payload['is_credit_holder'] === '1',
            'credit_owner_name' => $payload['credit_owner_name'],
            'credit_owner_cedula' => $payload['credit_owner_cedula'],
            'ownership_declaration_accepted' => true,
            'data_processing_accepted' => true,
        ];
    }

    private function latestOtp(): string
    {
        $message = $this->lastSmsMessage();
        $this->assertSame(1, preg_match('/\b(\d{6})\b/', $message, $matches));

        return $matches[1];
    }

    private function wrongOtp(string $otp): string
    {
        return $otp === '000000' ? '111111' : '000000';
    }

    private function lastSmsMessage(): string
    {
        $messages = FakeSmsGateway::sentMessages();
        $message = end($messages);

        $this->assertIsArray($message);

        return $message['message'];
    }

    private function seedMotoSequence(int $nextValue): void
    {
        RadicadoSequence::query()->create([
            'type' => CancellationType::MOTO,
            'next_value' => $nextValue,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeClient(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => '1234567890',
            'identity' => '1234567890',
            'name' => 'Client User',
            'email' => 'client@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ], $attributes));
    }

    private function makeAdvisor(): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::ADVISOR),
            'username' => 'ADVISOR01',
            'identity' => null,
            'name' => 'Advisor User',
            'email' => 'advisor@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function makePendingLienMoto(User $owner, User $creator): MotoCancellation
    {
        return MotoCancellation::query()->create([
            'otp_challenge_id' => $this->makeConsumedChallenge()->id,
            'radicado' => null,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
            'assigned_advisor_user_id' => $creator->id,
            'origin' => CancellationOrigin::ADVISOR,
            'status' => CancellationStatus::PENDIENTE_RADICACION,
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

    private function makeConsumedChallenge(): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase126-'.bin2hex(random_bytes(8)),
            'purpose' => OtpPurpose::CREATE_MOTO,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase126'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }

    private function grantAdvisor(PermissionKey $permissionKey): void
    {
        app(GrantRolePermissionAction::class)->execute(
            Role::query()->where('code', RoleCode::ADVISOR->value)->firstOrFail(),
            $permissionKey,
        );
    }

    private function actingAsReady(User $user): void
    {
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
                '_token' => 'phase04-token',
            ]);
    }
}
