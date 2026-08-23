<?php

namespace Tests\Feature\History;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Enums\ActivityType;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class ActivityAuditOperationalViewTest extends TestCase
{
    use RefreshPhaseDatabase;

    private int $nextRadicado = 13000;

    public function test_activity_and_audit_catalogues_and_log_baseline_are_reconciled(): void
    {
        $this->assertSame(
            ['CREATED', 'UPDATED', 'OWNER_REASSIGNED', 'RESPONSE_OBTAINED'],
            array_map(static fn (ActivityType $type): string => $type->value, ActivityType::cases()),
        );
        $this->assertSame(
            [
                'CANCELLATION_CREATED',
                'CANCELLATION_UPDATED',
                'OWNER_REASSIGNED',
                'RADICADO_SMS_RETRY_REQUESTED',
                'RESPONSE_OBTAINED',
                'EXPORT_GENERATED',
            ],
            array_map(static fn (AuditEventType $eventType): string => $eventType->value, AuditEventType::cases()),
        );
        $this->assertSame('daily', config('logging.default'));
        $this->assertSame(30, config('logging.channels.daily.days'));
    }

    public function test_case_history_requires_permission_and_paginates_activity_without_cross_product_leaks(): void
    {
        $advisor = $this->makeAdvisor('advisor-history', 'Advisor History');
        $client = $this->makeClient('1000002001', 'Client History');
        $moto = $this->makeMoto($client, $advisor);
        $credit = $this->makeCredit($client, $advisor);
        $this->createActivity(null, $credit, $advisor, ActivityType::UPDATED, 'credit-noise');
        $this->createAudit($moto, null, $advisor, AuditEventType::CANCELLATION_UPDATED, 'phase12-history-request');

        for ($index = 0; $index < 16; $index++) {
            $this->createActivity(
                $moto,
                null,
                $advisor,
                $index === 0 ? ActivityType::CREATED : ActivityType::UPDATED,
                $index === 0 ? 'page-two-marker' : 'page-one-marker-'.$index,
            );
        }

        $this->actingAsReady($advisor);
        $this->get(route('advisor.moto.activity', $moto))->assertForbidden();

        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_ACTIVITY_VIEW);

        $this->get(route('advisor.moto.activity', ['moto' => $moto, 'activity_page' => 2]))
            ->assertOk()
            ->assertSee('Historial Moto')
            ->assertSee('page-two-marker')
            ->assertSee('phase12-history-request')
            ->assertDontSee('credit-noise');

        $this->actingAsReady($client);
        $this->get(route('advisor.moto.activity', $moto))->assertForbidden();
    }

    public function test_update_audit_masks_changes_sanitizes_reason_and_preserves_request_id_correlation(): void
    {
        $advisor = $this->makeAdvisor('advisor-masking', 'Advisor Masking');
        $client = $this->makeClient('1000002002', 'Client Masking');
        $moto = $this->makeMoto($client, $advisor, ['holder_phone' => '+573001111111']);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_UPDATE);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_ACTIVITY_VIEW);
        $this->actingAsReady($advisor);

        $response = $this->patch(route('advisor.moto.update', $moto), [
            '_token' => 'phase12-token',
            'expected_version' => $moto->version,
            'reason' => 'Contactar ana@example.test password=supersecret +573001111111',
            'holder_phone' => '3002222222',
        ])->assertRedirect(route('advisor.moto.edit', $moto, absolute: false));
        $requestId = (string) $response->headers->get('X-Request-Id');

        $audit = Audit::query()->where('event_type', AuditEventType::CANCELLATION_UPDATED)->firstOrFail();
        $encodedMetadata = json_encode($audit->metadata, JSON_THROW_ON_ERROR);

        $this->assertSame($requestId, $audit->request_id);
        $this->assertStringNotContainsString('ana@example.test', $encodedMetadata);
        $this->assertStringNotContainsString('supersecret', $encodedMetadata);
        $this->assertStringNotContainsString('+573001111111', $encodedMetadata);
        $this->assertStringNotContainsString('+573002222222', $encodedMetadata);
        $this->assertStringContainsString('***********11', $encodedMetadata);
        $this->assertStringContainsString('***********22', $encodedMetadata);

        $this->get(route('advisor.moto.activity', $moto))
            ->assertOk()
            ->assertSee($requestId)
            ->assertDontSee('ana@example.test')
            ->assertDontSee('supersecret')
            ->assertDontSee('+573001111111')
            ->assertDontSee('+573002222222');
    }

    public function test_operational_audit_view_is_authorized_masks_metadata_and_hides_raw_paths(): void
    {
        $advisor = $this->makeAdvisor('advisor-audit', 'Advisor Audit');
        $this->createGlobalAudit($advisor);
        $this->actingAsReady($advisor);

        $this->get(route('advisor.operational.audit.index'))->assertForbidden();

        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_ACTIVITY_VIEW);

        $this->get(route('advisor.operational.audit.index'))
            ->assertOk()
            ->assertSee('Auditoria operacional')
            ->assertSee('Exportacion XLSX generada')
            ->assertSee('phase12-global-request')
            ->assertSee('Global')
            ->assertSee('cancelaciones-phase12.xlsx')
            ->assertDontSee('ana@example.test')
            ->assertDontSee('+573001234567')
            ->assertDontSee('supersecret')
            ->assertDontSee('/var/www/html/storage')
            ->assertDontSee('cancellations/private-path.xlsx')
            ->assertDontSee('Stack trace with raw exception');
    }

    public function test_sms_retry_is_audit_only_and_not_activity(): void
    {
        $advisor = $this->makeAdvisor('advisor-sms-history', 'Advisor Sms History');
        $client = $this->makeClient('1000002003', 'Client Sms History');
        $moto = $this->makeMoto($client, $advisor);
        $this->createInitialSmsAttempt($moto);
        $this->grant(RoleCode::ADVISOR, PermissionKey::RADICADO_SMS_RETRY);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_ACTIVITY_VIEW);
        $this->actingAsReady($advisor);

        $this->from(route('advisor.moto.edit', $moto))
            ->post(route('advisor.moto.sms.retry', $moto), [
                '_token' => 'phase12-token',
            ])->assertRedirect(route('advisor.moto.edit', $moto, absolute: false));

        $this->assertSame(0, Activity::query()->count());
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::RADICADO_SMS_RETRY_REQUESTED)->count());

        $this->get(route('advisor.moto.activity', $moto))
            ->assertOk()
            ->assertSee('No hay actividad funcional registrada.')
            ->assertSee('Reintento SMS radicado');

        $this->get(route('advisor.operational.audit.index'))
            ->assertOk()
            ->assertSee('Reintento SMS radicado')
            ->assertSee('***********67');
    }

    private function grant(RoleCode $roleCode, PermissionKey $permissionKey): void
    {
        app(GrantRolePermissionAction::class)->execute(
            Role::query()->where('code', $roleCode->value)->firstOrFail(),
            $permissionKey,
        );
    }

    private function makeClient(string $identity, string $name): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => $identity,
            'identity' => $identity,
            'name' => $name,
            'email' => $identity.'@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function makeAdvisor(string $username, string $name): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::ADVISOR),
            'username' => $username,
            'identity' => null,
            'name' => $name,
            'email' => $username.'@example.test',
            'phone' => '+573009999999',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMoto(User $owner, User $creator, array $overrides = []): MotoCancellation
    {
        /** @var MotoCancellation $moto */
        $moto = MotoCancellation::query()->create(array_merge([
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
        ], $overrides));

        return $moto;
    }

    private function makeCredit(User $owner, User $creator): CreditCancellation
    {
        /** @var CreditCancellation $credit */
        $credit = CreditCancellation::query()->create([
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

        return $credit;
    }

    private function makeOtpChallenge(OtpPurpose $purpose): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase12-'.bin2hex(random_bytes(8)),
            'purpose' => $purpose,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase12'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }

    private function createActivity(
        ?MotoCancellation $moto,
        ?CreditCancellation $credit,
        User $actor,
        ActivityType $type,
        string $marker,
    ): void {
        Activity::query()->create([
            'moto_cancellation_id' => $moto?->id,
            'credit_cancellation_id' => $credit?->id,
            'actor_user_id' => $actor->id,
            'type' => $type,
            'metadata' => [
                'event' => $type->value,
                'radicado' => $moto?->radicado ?? $credit?->radicado,
                'changed_fields' => [$marker],
            ],
        ]);
    }

    private function createAudit(
        ?MotoCancellation $moto,
        ?CreditCancellation $credit,
        User $actor,
        AuditEventType $eventType,
        string $requestId,
    ): void {
        Audit::query()->create([
            'moto_cancellation_id' => $moto?->id,
            'credit_cancellation_id' => $credit?->id,
            'actor_user_id' => $actor->id,
            'event_type' => $eventType,
            'request_id' => $requestId,
            'metadata' => [
                'event' => $eventType->value,
                'radicado' => $moto?->radicado ?? $credit?->radicado,
                'version' => 2,
            ],
        ]);
    }

    private function createGlobalAudit(User $actor): void
    {
        Audit::query()->create([
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::EXPORT_GENERATED,
            'request_id' => 'phase12-global-request',
            'metadata' => [
                'filename' => 'cancelaciones-phase12.xlsx',
                'row_count' => 12,
                'filters' => [
                    'holder_email' => 'ana@example.test',
                    'holder_phone' => '+573001234567',
                ],
                'reason' => 'password=supersecret',
                'stored_path' => 'cancellations/private-path.xlsx',
                'absolute_path' => '/var/www/html/storage/app/private/exports/cancellations/private-path.xlsx',
                'exception' => 'Stack trace with raw exception',
            ],
        ]);
    }

    private function createInitialSmsAttempt(MotoCancellation $moto): SmsAttempt
    {
        return SmsAttempt::query()->create([
            'moto_cancellation_id' => $moto->id,
            'purpose' => SmsPurpose::RADICADO,
            'status' => SmsAttemptStatus::SENT,
            'is_manual_retry' => false,
            'destination' => $moto->holder_phone,
            'completed_at' => now(),
        ]);
    }

    private function actingAsReady(User $user): void
    {
        RateLimiter::clear('radicado-sms-retry:user:'.$user->id);
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
                '_token' => 'phase12-token',
            ]);
    }
}
