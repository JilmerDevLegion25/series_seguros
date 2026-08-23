<?php

namespace Tests\Feature\Credit;

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
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayResult;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class CreditCancellationTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_public_credit_e2e_creates_after_valid_otp_and_preserves_credit_number(): void
    {
        $this->seedSequence(CancellationType::CREDIT, 700);

        $this->post(route('public.credit.store'), $this->validPayload([
            'credit_number' => ' 0000123400 ',
            'holder_email' => 'CLIENT@EXAMPLE.TEST',
            'cancel_personal_accidents' => '1',
            'cancel_unemployment_insurance' => '1',
        ]))->assertOk();

        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();

        $this->assertSame(0, CreditCancellation::query()->count());
        $this->assertSame(0, User::query()->count());

        $this->post(route('public.credit.complete'), [
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
            'owner_user_id' => 999,
            'created_by_user_id' => 999,
            'status' => CancellationStatus::RESPUESTA_OBTENIDA->value,
            'radicado' => 999,
            'version' => 99,
            'payload' => ['credit_number' => 'TAMPERED'],
        ])->assertOk()
            ->assertSee('Solicitud recibida')
            ->assertSee('Radicado Credit')
            ->assertSee('700')
            ->assertSee('Su solicitud ha sido enviada con exito')
            ->assertSee('portal.correovalle.com/progreser');

        $credit = CreditCancellation::query()->firstOrFail();
        $client = User::query()->firstOrFail();

        $this->assertSame(700, $credit->radicado);
        $this->assertSame($client->id, $credit->owner_user_id);
        $this->assertSame($client->id, $credit->created_by_user_id);
        $this->assertSame(CancellationOrigin::PUBLIC, $credit->origin);
        $this->assertSame(CancellationStatus::EN_GESTION, $credit->status);
        $this->assertSame(1, $credit->version);
        $this->assertSame('0000123400', $credit->credit_number);
        $this->assertTrue($credit->cancel_personal_accidents);
        $this->assertTrue($credit->cancel_unemployment_insurance);
        $this->assertSame('client@example.test', $credit->holder_email);
        $this->assertTrue($client->must_change_password);
        $this->assertTrue(Hash::check((string) config('authentication.client_initial_password'), $client->password));
        $this->assertTrue($challenge->fresh()->isConsumed());
        $this->assertNull($challenge->fresh()->encrypted_payload);
        $this->assertGuest();

        $this->assertSame(1, Activity::query()->where('type', ActivityType::CREATED)->whereNotNull('credit_cancellation_id')->count());
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::CANCELLATION_CREATED)->whereNotNull('credit_cancellation_id')->count());
        $this->assertSame(1, SmsAttempt::query()->where('purpose', SmsPurpose::RADICADO)->where('status', SmsAttemptStatus::SENT)->whereNotNull('credit_cancellation_id')->count());
        $this->assertStringContainsString('700', $this->lastSmsMessage());
    }

    public function test_credit_insurance_invariant_rejects_none_and_allows_both(): void
    {
        $this->seedSequence(CancellationType::CREDIT, 1);

        $this->from(route('public.credit.create'))
            ->post(route('public.credit.store'), $this->validPayload([
                'cancel_personal_accidents' => '0',
                'cancel_unemployment_insurance' => '0',
            ]))
            ->assertRedirect(route('public.credit.create', absolute: false))
            ->assertSessionHasErrors(['cancel_personal_accidents']);

        $this->assertSame(0, OtpChallenge::query()->count());

        $this->post(route('public.credit.store'), $this->validPayload([
            'cancel_personal_accidents' => '1',
            'cancel_unemployment_insurance' => '1',
        ]))->assertOk();

        $this->assertSame(1, OtpChallenge::query()->count());
    }

    public function test_invalid_expired_or_consumed_otp_creates_nothing(): void
    {
        $this->seedSequence(CancellationType::CREDIT, 100);

        $this->post(route('public.credit.store'), $this->validPayload(['holder_cedula' => '1111111111', 'holder_phone' => '3001111111']))->assertOk();
        $invalid = OtpChallenge::query()->latest('id')->firstOrFail();
        $invalidOtp = $this->latestOtp();
        $this->post(route('public.credit.complete'), [
            'challenge_reference' => $invalid->public_reference,
            'otp' => $this->wrongOtp($invalidOtp),
        ])->assertSessionHasErrors(['otp']);

        $this->post(route('public.credit.store'), $this->validPayload(['holder_cedula' => '2222222222', 'holder_phone' => '3002222222']))->assertOk();
        $expired = OtpChallenge::query()->latest('id')->firstOrFail();
        $expiredOtp = $this->latestOtp();
        $expired->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->post(route('public.credit.complete'), [
            'challenge_reference' => $expired->public_reference,
            'otp' => $expiredOtp,
        ])->assertSessionHasErrors(['otp']);

        $consumed = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_CREDIT,
            destination: '+573003333333',
            payload: $this->payloadForChallenge($this->validPayload([
                'holder_cedula' => '3333333333',
                'holder_phone' => '3003333333',
            ])),
        ))->challenge;
        $consumedOtp = $this->latestOtp();
        app(VerifyAndConsumeOtpChallengeAction::class)->execute($consumed->public_reference, $consumedOtp, '127.0.0.20');

        $this->post(route('public.credit.complete'), [
            'challenge_reference' => $consumed->public_reference,
            'otp' => $consumedOtp,
        ])->assertSessionHasErrors(['otp']);

        $this->assertSame(0, CreditCancellation::query()->count());
    }

    public function test_payload_cannot_be_replaced_and_existing_client_is_refreshed_without_password_reset(): void
    {
        $this->seedSequence(CancellationType::CREDIT, 200);
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

        $this->post(route('public.credit.store'), $this->validPayload([
            'holder_name' => 'New Credit Client',
            'holder_email' => 'new@example.test',
            'holder_phone' => '3001234567',
            'credit_number' => '00077',
        ]))->assertOk();

        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();

        $this->post(route('public.credit.complete'), [
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
            'holder_name' => 'Browser Tamper',
            'credit_number' => 'BAD999',
        ])->assertOk();

        $client->refresh();
        $credit = CreditCancellation::query()->firstOrFail();

        $this->assertSame('New Credit Client', $client->name);
        $this->assertSame('new@example.test', $client->email);
        $this->assertSame('+573001234567', $client->phone);
        $this->assertSame($originalPassword, $client->password);
        $this->assertFalse($client->must_change_password);
        $this->assertSame('00077', $credit->credit_number);
        $this->assertSame('New Credit Client', $credit->holder_name);
    }

    public function test_sms_failure_does_not_rollback_credit_creation(): void
    {
        $this->seedSequence(CancellationType::CREDIT, 300);

        $this->post(route('public.credit.store'), $this->validPayload())->assertOk();
        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();

        FakeSmsGateway::fakeNextResult(SmsGatewayResult::failed('PROVIDER_DOWN', 'Provider unavailable'));

        $this->post(route('public.credit.complete'), [
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
        ])->assertOk();

        $this->assertSame(1, CreditCancellation::query()->count());
        $attempt = SmsAttempt::query()->firstOrFail();
        $this->assertSame(SmsAttemptStatus::FAILED, $attempt->status);
        $this->assertSame('PROVIDER_DOWN', $attempt->safe_error_code);
    }

    public function test_advisor_requires_permission_and_still_needs_otp(): void
    {
        $this->seedSequence(CancellationType::CREDIT, 400);
        $advisor = $this->makeAdvisor();

        $this->actingAsReady($advisor);
        $this->post(route('advisor.credit.store'), $this->validPayload())
            ->assertForbidden();

        $this->grantAdvisor(PermissionKey::CANCELLATIONS_CREATE);
        $this->post(route('advisor.credit.store'), $this->validPayload([
            'holder_cedula' => '4444444444',
            'holder_phone' => '3004444444',
        ]))->assertOk();

        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();
        $this->assertSame(0, CreditCancellation::query()->count());

        $this->post(route('advisor.credit.complete'), [
            'challenge_reference' => $challenge->public_reference,
            'otp' => $this->wrongOtp($otp),
        ])->assertSessionHasErrors(['otp']);
        $this->assertSame(0, CreditCancellation::query()->count());

        $this->post(route('advisor.credit.complete'), [
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
        ])->assertOk();

        $credit = CreditCancellation::query()->firstOrFail();

        $this->assertSame(CancellationOrigin::ADVISOR, $credit->origin);
        $this->assertSame($advisor->id, $credit->created_by_user_id);
        $this->assertNotSame($advisor->id, $credit->owner_user_id);
    }

    public function test_moto_and_credit_can_use_same_radicado_number_from_independent_sequences(): void
    {
        $this->seedSequence(CancellationType::MOTO, 486);
        $this->seedSequence(CancellationType::CREDIT, 486);

        $this->post(route('public.moto.store'), $this->validMotoPayload())->assertOk();
        $motoChallenge = OtpChallenge::query()->latest('id')->firstOrFail();
        $this->post(route('public.moto.complete'), [
            'challenge_reference' => $motoChallenge->public_reference,
            'otp' => $this->latestOtp(),
        ])->assertOk();

        $this->post(route('public.credit.store'), $this->validPayload([
            'holder_cedula' => '9876543210',
            'holder_phone' => '3009876543',
        ]))->assertOk();
        $creditChallenge = OtpChallenge::query()->latest('id')->firstOrFail();
        $this->post(route('public.credit.complete'), [
            'challenge_reference' => $creditChallenge->public_reference,
            'otp' => $this->latestOtp(),
        ])->assertOk();

        $this->assertSame(486, MotoCancellation::query()->firstOrFail()->radicado);
        $this->assertSame(486, CreditCancellation::query()->firstOrFail()->radicado);
    }

    public function test_double_completion_is_idempotent_and_does_not_duplicate_sms(): void
    {
        $this->seedSequence(CancellationType::CREDIT, 500);

        $this->post(route('public.credit.store'), $this->validPayload())->assertOk();
        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();

        $this->post(route('public.credit.complete'), [
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
        ])->assertOk();

        $this->post(route('public.credit.complete'), [
            'challenge_reference' => $challenge->public_reference,
            'otp' => $otp,
        ])->assertOk()->assertSee('ya habia sido completada');

        $this->assertSame(1, CreditCancellation::query()->count());
        $this->assertSame(1, SmsAttempt::query()->where('purpose', SmsPurpose::RADICADO)->count());
        $this->assertSame(501, RadicadoSequence::query()->where('type', CancellationType::CREDIT)->value('next_value'));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'holder_name' => 'Credit Client',
            'holder_cedula' => '1234567890',
            'credit_number' => '00012345',
            'holder_phone' => '3001234567',
            'holder_email' => 'credit@example.test',
            'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS->value,
            'cancel_personal_accidents' => '1',
            'cancel_unemployment_insurance' => '0',
            'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL->value,
            'credit_holder_declaration_accepted' => '1',
            'data_processing_accepted' => '1',
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function validMotoPayload(): array
    {
        return [
            'holder_name' => 'Moto Client',
            'holder_cedula' => '1234567890',
            'property_lien_adeinco' => '1',
            'plate' => 'ABC123',
            'holder_phone' => '3001234567',
            'holder_email' => 'moto@example.test',
            'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS->value,
            'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL->value,
            'is_credit_holder' => '0',
            'credit_owner_name' => 'Credit Owner',
            'credit_owner_cedula' => '9876543210',
            'ownership_declaration_accepted' => '1',
            'data_processing_accepted' => '1',
        ];
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, bool|int|string|null>
     */
    private function payloadForChallenge(array $payload): array
    {
        return [
            'payload_schema' => 'credit_create_v1',
            'origin' => CancellationOrigin::PUBLIC->value,
            'created_by_user_id' => null,
            'holder_name' => $payload['holder_name'],
            'holder_cedula' => $payload['holder_cedula'],
            'credit_number' => $payload['credit_number'],
            'holder_phone' => '+57'.$payload['holder_phone'],
            'holder_email' => $payload['holder_email'],
            'cancellation_reason' => $payload['cancellation_reason'],
            'cancel_personal_accidents' => $payload['cancel_personal_accidents'] === '1',
            'cancel_unemployment_insurance' => $payload['cancel_unemployment_insurance'] === '1',
            'cancellation_information_source' => $payload['cancellation_information_source'],
            'credit_holder_declaration_accepted' => true,
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

    private function seedSequence(CancellationType $type, int $nextValue): void
    {
        RadicadoSequence::query()->create([
            'type' => $type,
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
            ]);
    }
}
