<?php

namespace Tests\Feature\Cancellations;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Actions\Authorization\RevokeRolePermissionAction;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\OtpPurpose;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class EditReassignmentTest extends TestCase
{
    use RefreshPhaseDatabase;

    private int $nextRadicado = 1000;

    public function test_advisor_can_edit_moto_in_management_with_changeset_and_immutable_fields(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1234567890');
        $moto = $this->makeMoto($owner, $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);
        $this->actingAsReady($advisor);

        $this->patch(route('advisor.moto.update', $moto), [
            '_token' => 'phase06-token',
            'expected_version' => '1',
            'reason' => 'Correccion operativa documentada',
            'holder_name' => 'Moto Holder Editado',
            'holder_phone' => '3007654321',
            'holder_email' => 'EDITED@EXAMPLE.TEST',
            'property_lien_adeinco' => '0',
            'plate' => ' xyz987 ',
            'cancellation_reason' => MotoCancellationReason::DESEA_OTRA_ASEGURADORA->value,
            'cancellation_information_source' => MotoInformationSource::NEGOCIADOR_CARTERA->value,
            'is_credit_holder' => '1',
            'credit_owner_name' => 'Browser Tamper',
            'credit_owner_cedula' => '9999999999',
            'holder_cedula' => '9999999999',
            'owner_user_id' => 999,
            'created_by_user_id' => 999,
            'assigned_advisor_user_id' => 999,
            'status' => CancellationStatus::RESPUESTA_OBTENIDA->value,
            'radicado' => 999,
            'version' => 99,
        ])->assertRedirect(route('advisor.moto.edit', $moto, absolute: false));

        $moto->refresh();
        $audit = Audit::query()->where('event_type', AuditEventType::CANCELLATION_UPDATED)->firstOrFail();

        $this->assertSame(2, $moto->version);
        $this->assertSame('Moto Holder Editado', $moto->holder_name);
        $this->assertSame('+573007654321', $moto->holder_phone);
        $this->assertSame('edited@example.test', $moto->holder_email);
        $this->assertFalse($moto->property_lien_adeinco);
        $this->assertSame('XYZ987', $moto->plate);
        $this->assertSame(MotoCancellationReason::DESEA_OTRA_ASEGURADORA, $moto->cancellation_reason);
        $this->assertSame(MotoInformationSource::NEGOCIADOR_CARTERA, $moto->cancellation_information_source);
        $this->assertTrue($moto->is_credit_holder);
        $this->assertNull($moto->credit_owner_name);
        $this->assertNull($moto->credit_owner_cedula);
        $this->assertSame('1234567890', $moto->holder_cedula);
        $this->assertSame($owner->id, $moto->owner_user_id);
        $this->assertSame($advisor->id, $moto->created_by_user_id);
        $this->assertSame($advisor->id, $moto->assigned_advisor_user_id);
        $this->assertSame(1000, $moto->radicado);
        $this->assertSame(CancellationStatus::EN_GESTION, $moto->status);
        $this->assertSame(1, Activity::query()->where('type', ActivityType::UPDATED)->where('moto_cancellation_id', $moto->id)->count());
        $this->assertContains('plate', $audit->metadata['changed_fields']);
        $plateChange = $this->changeByField($audit, 'plate');
        $this->assertSame('ABC123', $plateChange['before']);
        $this->assertSame('XYZ987', $plateChange['after']);
    }

    public function test_advisor_can_edit_credit_and_cannot_break_insurance_invariant(): void
    {
        $advisor = $this->makeAdvisor();
        $credit = $this->makeCredit($this->makeClient('2234567890'), $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);
        $this->actingAsReady($advisor);

        $this->from(route('advisor.credit.edit', $credit))
            ->patch(route('advisor.credit.update', $credit), [
                '_token' => 'phase06-token',
                'expected_version' => '1',
                'reason' => 'Intento sin seguros',
                'cancel_personal_accidents' => '0',
                'cancel_unemployment_insurance' => '0',
            ])
            ->assertRedirect(route('advisor.credit.edit', $credit, absolute: false))
            ->assertSessionHasErrors(['cancel_personal_accidents']);

        $credit->refresh();
        $this->assertSame(1, $credit->version);

        $this->patch(route('advisor.credit.update', $credit), [
            '_token' => 'phase06-token',
            'expected_version' => '1',
            'reason' => 'Correccion de datos del credito',
            'holder_name' => 'Credit Holder Editado',
            'holder_phone' => '3008887777',
            'holder_email' => 'CREDIT.EDITED@EXAMPLE.TEST',
            'credit_number' => ' 000999 ',
            'cancellation_reason' => MotoCancellationReason::DESEA_OTRA_ASEGURADORA->value,
            'cancel_personal_accidents' => '1',
            'cancel_unemployment_insurance' => '1',
            'cancellation_information_source' => MotoInformationSource::NEGOCIADOR_CARTERA->value,
            'holder_cedula' => '9999999999',
            'assigned_advisor_user_id' => 999,
            'radicado' => 999,
            'status' => CancellationStatus::RESPUESTA_OBTENIDA->value,
        ])->assertRedirect(route('advisor.credit.edit', $credit, absolute: false));

        $credit->refresh();
        $audit = Audit::query()->where('event_type', AuditEventType::CANCELLATION_UPDATED)->firstOrFail();

        $this->assertSame(2, $credit->version);
        $this->assertSame('Credit Holder Editado', $credit->holder_name);
        $this->assertSame('+573008887777', $credit->holder_phone);
        $this->assertSame('credit.edited@example.test', $credit->holder_email);
        $this->assertSame('000999', $credit->credit_number);
        $this->assertTrue($credit->cancel_personal_accidents);
        $this->assertTrue($credit->cancel_unemployment_insurance);
        $this->assertSame('2234567890', $credit->holder_cedula);
        $this->assertSame($advisor->id, $credit->assigned_advisor_user_id);
        $this->assertSame(1000, $credit->radicado);
        $this->assertSame(CancellationStatus::EN_GESTION, $credit->status);
        $creditNumberChange = $this->changeByField($audit, 'credit_number');
        $this->assertSame('00012345', $creditNumberChange['before']);
        $this->assertSame('000999', $creditNumberChange['after']);
    }

    public function test_permission_grant_and_revoke_affect_edit_route_on_next_request(): void
    {
        $advisor = $this->makeAdvisor();
        $moto = $this->makeMoto($this->makeClient('3234567890'), $advisor);
        $advisorRole = Role::query()->where('code', RoleCode::ADVISOR->value)->firstOrFail();
        $this->actingAsReady($advisor);

        $this->get(route('advisor.moto.edit', $moto))->assertForbidden();

        app(GrantRolePermissionAction::class)->execute($advisorRole, PermissionKey::CANCELLATIONS_UPDATE);
        $this->get(route('advisor.moto.edit', $moto))->assertOk()->assertSee('_token', false);

        app(RevokeRolePermissionAction::class)->execute($advisorRole, PermissionKey::CANCELLATIONS_UPDATE);
        $this->get(route('advisor.moto.edit', $moto))->assertForbidden();
    }

    public function test_client_and_advisor_without_permission_cannot_edit(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('4234567890');
        $moto = $this->makeMoto($owner, $advisor);

        $this->actingAsReady($advisor);
        $this->patch(route('advisor.moto.update', $moto), [
            '_token' => 'phase06-token',
            'expected_version' => '1',
            'reason' => 'Sin permiso suficiente',
            'plate' => 'DENIED',
        ])->assertForbidden();

        $this->actingAsReady($owner);
        $this->patch(route('advisor.moto.update', $moto), [
            '_token' => 'phase06-token',
            'expected_version' => '1',
            'reason' => 'Cliente sin permiso',
            'plate' => 'DENIED',
        ])->assertForbidden();

        $moto->refresh();
        $this->assertSame('ABC123', $moto->plate);
        $this->assertSame(1, $moto->version);
    }

    public function test_terminal_cancellation_is_not_editable(): void
    {
        $advisor = $this->makeAdvisor();
        $moto = $this->makeMoto($this->makeClient('5234567890'), $advisor, [
            'status' => CancellationStatus::RESPUESTA_OBTENIDA,
        ]);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);
        $this->actingAsReady($advisor);

        $this->from(route('advisor.moto.edit', $moto))
            ->patch(route('advisor.moto.update', $moto), [
                '_token' => 'phase06-token',
                'expected_version' => '1',
                'reason' => 'Intento sobre terminal',
                'plate' => 'TERM01',
            ])
            ->assertRedirect(route('advisor.moto.edit', $moto, absolute: false))
            ->assertSessionHasErrors(['status']);

        $moto->refresh();
        $this->assertSame('ABC123', $moto->plate);
        $this->assertSame(1, $moto->version);
        $this->assertSame(0, Activity::query()->count());
        $this->assertSame(0, Audit::query()->count());
    }

    public function test_stale_expected_version_is_rejected_without_mutation(): void
    {
        $advisor = $this->makeAdvisor();
        $credit = $this->makeCredit($this->makeClient('6234567890'), $advisor, [
            'version' => 2,
        ]);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);
        $this->actingAsReady($advisor);

        $this->from(route('advisor.credit.edit', $credit))
            ->patch(route('advisor.credit.update', $credit), [
                '_token' => 'phase06-token',
                'expected_version' => '1',
                'reason' => 'Version anterior',
                'credit_number' => 'STALE01',
            ])
            ->assertRedirect(route('advisor.credit.edit', $credit, absolute: false))
            ->assertSessionHasErrors(['expected_version']);

        $credit->refresh();
        $this->assertSame('00012345', $credit->credit_number);
        $this->assertSame(2, $credit->version);
        $this->assertSame(0, Activity::query()->count());
        $this->assertSame(0, Audit::query()->count());
    }

    public function test_noop_edit_does_not_create_history_or_increment_version(): void
    {
        $advisor = $this->makeAdvisor();
        $moto = $this->makeMoto($this->makeClient('7234567890'), $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);
        $this->actingAsReady($advisor);

        $this->patch(route('advisor.moto.update', $moto), [
            '_token' => 'phase06-token',
            'expected_version' => '1',
            'reason' => 'Revision sin cambios',
            'holder_name' => $moto->holder_name,
            'holder_phone' => $moto->holder_phone,
            'holder_email' => $moto->holder_email,
            'property_lien_adeinco' => '1',
            'plate' => $moto->plate,
            'cancellation_reason' => $moto->cancellation_reason->value,
            'cancellation_information_source' => $moto->cancellation_information_source->value,
            'is_credit_holder' => '0',
            'credit_owner_name' => $moto->credit_owner_name,
            'credit_owner_cedula' => $moto->credit_owner_cedula,
        ])->assertRedirect(route('advisor.moto.edit', $moto, absolute: false));

        $moto->refresh();
        $this->assertSame(1, $moto->version);
        $this->assertSame(0, Activity::query()->count());
        $this->assertSame(0, Audit::query()->count());
    }

    public function test_moto_reassignment_changes_only_assigned_advisor(): void
    {
        $advisor = $this->makeAdvisor();
        $newAdvisor = $this->makeAdvisor();
        $owner = $this->makeClient('8234567890');
        $moto = $this->makeMoto($owner, $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_REASSIGN);
        $this->actingAsReady($advisor);

        $this->post(route('advisor.moto.reassign.store', $moto), [
            '_token' => 'phase06-token',
            'expected_version' => '1',
            'reason' => 'Nuevo Advisor asignado',
            'assigned_advisor_user_id' => $newAdvisor->id,
            'holder_name' => 'Nuevo Titular Moto',
            'holder_cedula' => '9234567890',
            'holder_phone' => '3001112222',
            'holder_email' => 'NUEVO.MOTO@EXAMPLE.TEST',
            'plate' => 'TAMPERED',
            'radicado' => 999,
        ])->assertRedirect(route('advisor.moto.edit', $moto, absolute: false));

        $moto->refresh();
        $audit = Audit::query()->where('event_type', AuditEventType::OWNER_REASSIGNED)->firstOrFail();

        $this->assertSame($owner->id, $moto->owner_user_id);
        $this->assertSame($advisor->id, $moto->created_by_user_id);
        $this->assertSame($newAdvisor->id, $moto->assigned_advisor_user_id);
        $this->assertSame($owner->name, $moto->holder_name);
        $this->assertSame('8234567890', $moto->holder_cedula);
        $this->assertSame((string) $owner->phone, $moto->holder_phone);
        $this->assertSame((string) $owner->email, $moto->holder_email);
        $this->assertSame('ABC123', $moto->plate);
        $this->assertSame(1000, $moto->radicado);
        $this->assertSame(2, $moto->version);
        $this->assertSame(1, Activity::query()->where('type', ActivityType::OWNER_REASSIGNED)->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(['assigned_advisor_user_id'], $audit->metadata['changed_fields']);
        $this->assertNotContains('plate', $audit->metadata['changed_fields']);
        $this->assertNotContains('owner_user_id', $audit->metadata['changed_fields']);
        $this->assertNull(User::query()->where('identity', '9234567890')->first());
    }

    public function test_credit_reassignment_requires_reason_and_changes_only_assigned_advisor(): void
    {
        $advisor = $this->makeAdvisor();
        $newAdvisor = $this->makeAdvisor();
        $owner = $this->makeClient('1134567890');
        $credit = $this->makeCredit($owner, $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_REASSIGN);
        $this->actingAsReady($advisor);

        $this->from(route('advisor.credit.reassign', $credit))
            ->post(route('advisor.credit.reassign.store', $credit), [
                '_token' => 'phase06-token',
                'expected_version' => '1',
                'reason' => '',
                'assigned_advisor_user_id' => $newAdvisor->id,
            ])
            ->assertRedirect(route('advisor.credit.reassign', $credit, absolute: false))
            ->assertSessionHasErrors(['reason']);

        $this->post(route('advisor.credit.reassign.store', $credit), [
            '_token' => 'phase06-token',
            'expected_version' => '1',
            'reason' => 'Nuevo Advisor asignado',
            'assigned_advisor_user_id' => $newAdvisor->id,
            'holder_name' => 'Nuevo Titular Credit',
            'holder_cedula' => '2134567890',
            'holder_phone' => '3003334444',
            'holder_email' => 'nuevo.credit@example.test',
            'credit_number' => 'TAMPERED',
        ])->assertRedirect(route('advisor.credit.edit', $credit, absolute: false));

        $credit->refresh();
        $audit = Audit::query()->where('event_type', AuditEventType::OWNER_REASSIGNED)->firstOrFail();

        $this->assertSame($owner->id, $credit->owner_user_id);
        $this->assertSame($advisor->id, $credit->created_by_user_id);
        $this->assertSame($newAdvisor->id, $credit->assigned_advisor_user_id);
        $this->assertSame($owner->name, $credit->holder_name);
        $this->assertSame('1134567890', $credit->holder_cedula);
        $this->assertSame((string) $owner->phone, $credit->holder_phone);
        $this->assertSame((string) $owner->email, $credit->holder_email);
        $this->assertSame('00012345', $credit->credit_number);
        $this->assertSame(1000, $credit->radicado);
        $this->assertSame(2, $credit->version);
        $this->assertSame(['assigned_advisor_user_id'], $audit->metadata['changed_fields']);
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::OWNER_REASSIGNED)->where('credit_cancellation_id', $credit->id)->count());
        $this->assertNull(User::query()->where('identity', '2134567890')->first());
    }

    public function test_stale_reassignment_fails_for_moto_and_credit(): void
    {
        $advisor = $this->makeAdvisor();
        $newAdvisor = $this->makeAdvisor();
        $moto = $this->makeMoto($this->makeClient('1334567890'), $advisor, ['version' => 2]);
        $credit = $this->makeCredit($this->makeClient('1434567890'), $advisor, ['version' => 2]);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_REASSIGN);
        $this->actingAsReady($advisor);

        $this->from(route('advisor.moto.reassign', $moto))
            ->post(route('advisor.moto.reassign.store', $moto), [
                '_token' => 'phase06-token',
                'expected_version' => '1',
                'reason' => 'Version anterior',
                'assigned_advisor_user_id' => $newAdvisor->id,
            ])
            ->assertRedirect(route('advisor.moto.reassign', $moto, absolute: false))
            ->assertSessionHasErrors(['expected_version']);

        $this->from(route('advisor.credit.reassign', $credit))
            ->post(route('advisor.credit.reassign.store', $credit), [
                '_token' => 'phase06-token',
                'expected_version' => '1',
                'reason' => 'Version anterior',
                'assigned_advisor_user_id' => $newAdvisor->id,
            ])
            ->assertRedirect(route('advisor.credit.reassign', $credit, absolute: false))
            ->assertSessionHasErrors(['expected_version']);

        $moto->refresh();
        $credit->refresh();

        $this->assertSame($advisor->id, $moto->assigned_advisor_user_id);
        $this->assertSame($advisor->id, $credit->assigned_advisor_user_id);
        $this->assertSame(2, $moto->version);
        $this->assertSame(2, $credit->version);
        $this->assertSame(0, Activity::query()->count());
        $this->assertSame(0, Audit::query()->count());
    }

    private function changeByField(Audit $audit, string $field): array
    {
        foreach ($audit->metadata['changes'] as $change) {
            if ($change['field'] === $field) {
                return $change;
            }
        }

        $this->fail('Change not found: '.$field);
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMoto(User $owner, User $creator, array $overrides = []): MotoCancellation
    {
        return MotoCancellation::query()->create(array_merge([
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
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCredit(User $owner, User $creator, array $overrides = []): CreditCancellation
    {
        return CreditCancellation::query()->create(array_merge([
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
        ], $overrides));
    }

    private function makeOtpChallenge(OtpPurpose $purpose): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase06-'.bin2hex(random_bytes(8)),
            'purpose' => $purpose,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase06'),
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
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
                '_token' => 'phase06-token',
            ]);
    }
}
