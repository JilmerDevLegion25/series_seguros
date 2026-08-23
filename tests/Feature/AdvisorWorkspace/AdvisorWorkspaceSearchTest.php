<?php

namespace Tests\Feature\AdvisorWorkspace;

use App\Actions\Authorization\GrantRolePermissionAction;
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
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Models\Response as CancellationResponse;
use App\Models\Role;
use App\Models\SmsAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class AdvisorWorkspaceSearchTest extends TestCase
{
    use RefreshPhaseDatabase;

    private int $nextRadicado = 10000;

    public function test_workspace_requires_advisor_view_permission_and_denies_client_even_with_view_grant(): void
    {
        $advisor = $this->makeAdvisor('viewer-a', 'Advisor Viewer');
        $client = $this->makeClient('1000000001', 'Client Workspace');
        $this->makeMoto($client, $advisor, ['holder_name' => 'Moto Visible']);

        $this->actingAsReady($advisor);
        $this->get(route('advisor.cancellations.index'))->assertForbidden();

        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_VIEW);
        $this->get(route('advisor.cancellations.index'))
            ->assertOk()
            ->assertSee('Workspace Asesor')
            ->assertSee('Moto Visible');

        $this->grant(RoleCode::CLIENT, PermissionKey::CANCELLATIONS_VIEW);
        $this->actingAsReady($client);
        $this->get(route('advisor.cancellations.index'))->assertForbidden();
    }

    public function test_global_scope_returns_moto_and_credit_assigned_to_different_advisors(): void
    {
        $viewer = $this->makeAdvisor('viewer-b', 'Advisor Global');
        $otherAdvisor = $this->makeAdvisor('advisor-other', 'Advisor Other');
        $clientA = $this->makeClient('1000000002', 'Client Global A');
        $clientB = $this->makeClient('1000000003', 'Client Global B');
        $moto = $this->makeMoto($clientA, $viewer, [
            'holder_name' => 'Scope Moto',
            'plate' => 'SCP123',
            'assigned_advisor_user_id' => $otherAdvisor->id,
        ]);
        $credit = $this->makeCredit($clientB, $otherAdvisor, [
            'holder_name' => 'Scope Credit',
            'credit_number' => '000777',
            'assigned_advisor_user_id' => $viewer->id,
        ]);
        $this->createCreditResponse($credit, $viewer);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_VIEW);
        $this->actingAsReady($viewer);

        $this->get(route('advisor.cancellations.index'))
            ->assertOk()
            ->assertSee('Scope Moto')
            ->assertSee('SCP123')
            ->assertSee('Scope Credit')
            ->assertSee('000777')
            ->assertSee((string) $moto->radicado)
            ->assertSee((string) $credit->radicado);
    }

    public function test_search_filters_cover_shared_and_product_specific_fields(): void
    {
        $advisorOne = $this->makeAdvisor('advisor-one', 'Advisor One');
        $advisorTwo = $this->makeAdvisor('advisor-two', 'Advisor Two');
        $ownerOne = $this->makeClient('1000000004', 'Ana Searchable');
        $ownerTwo = $this->makeClient('1000000005', 'Bruno Searchable');
        $sharedRadicado = 12000;
        $moto = $this->makeMoto($ownerOne, $advisorOne, [
            'radicado' => $sharedRadicado,
            'holder_name' => 'Ana Maria Moto',
            'holder_email' => 'ana@example.test',
            'holder_phone' => '+573001111111',
            'holder_cedula' => '1000000004',
            'plate' => 'ANA123',
            'assigned_advisor_user_id' => $advisorOne->id,
            'created_at' => '2026-08-18 10:00:00',
            'updated_at' => '2026-08-18 10:00:00',
        ]);
        $credit = $this->makeCredit($ownerTwo, $advisorTwo, [
            'radicado' => $sharedRadicado,
            'holder_name' => 'Bruno Credit',
            'holder_email' => 'bruno@example.test',
            'holder_phone' => '+573002222222',
            'holder_cedula' => '1000000005',
            'credit_number' => '000ABC999',
            'assigned_advisor_user_id' => $advisorTwo->id,
            'status' => CancellationStatus::RESPUESTA_OBTENIDA,
            'created_at' => '2026-08-19 10:00:00',
            'updated_at' => '2026-08-19 10:00:00',
        ]);
        $this->makeMoto($ownerTwo, $advisorTwo, [
            'holder_name' => 'Noise Moto',
            'holder_email' => 'noise@example.test',
            'plate' => 'NOI123',
            'created_at' => '2026-08-17 10:00:00',
            'updated_at' => '2026-08-17 10:00:00',
        ]);
        $this->createCreditResponse($credit, $advisorOne);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_VIEW);
        $this->actingAsReady($advisorOne);

        $this->assertSearchShowsOnly(['holder_name' => 'Ana Maria'], 'Ana Maria Moto', ['Bruno Credit', 'Noise Moto']);
        $this->assertSearchShowsOnly(['holder_email' => 'ANA@EXAMPLE.TEST'], 'Ana Maria Moto', ['Bruno Credit']);
        $this->assertSearchShowsOnly(['holder_phone' => '3002222222'], 'Bruno Credit', ['Ana Maria Moto']);
        $this->assertSearchShowsOnly(['holder_cedula' => '1.000.000.004'], 'Ana Maria Moto', ['Bruno Credit']);
        $this->assertSearchShowsOnly(['type' => 'MOTO'], 'Ana Maria Moto', ['Bruno Credit']);
        $this->assertSearchShowsOnly(['type' => 'CREDIT'], 'Bruno Credit', ['Ana Maria Moto']);
        $this->assertSearchShowsOnly(['status' => CancellationStatus::RESPUESTA_OBTENIDA->value], 'Bruno Credit', ['Ana Maria Moto']);
        $this->assertSearchShowsOnly(['assigned_advisor_user_id' => $advisorTwo->id], 'Bruno Credit', ['Ana Maria Moto']);
        $this->assertSearchShowsOnly(['plate' => ' ana123 '], 'Ana Maria Moto', ['Bruno Credit']);
        $this->assertSearchShowsOnly(['credit_number' => '000ABC999'], 'Bruno Credit', ['Ana Maria Moto']);
        $this->assertSearchShowsOnly(['created_from' => '2026-08-19', 'created_to' => '2026-08-19'], 'Bruno Credit', ['Ana Maria Moto', 'Noise Moto']);

        $this->get(route('advisor.cancellations.index', ['radicado' => $sharedRadicado]))
            ->assertOk()
            ->assertSee('Ana Maria Moto')
            ->assertSee('Bruno Credit');

        $this->assertSame($sharedRadicado, $moto->radicado);
    }

    public function test_invalid_filter_combinations_and_injection_inputs_are_safe(): void
    {
        $advisor = $this->makeAdvisor('advisor-safe', 'Advisor Safe');
        $client = $this->makeClient('1000000006', 'Client Safe');
        $this->makeMoto($client, $advisor, ['holder_name' => 'Safe Moto']);
        $this->makeCredit($client, $advisor, ['holder_name' => 'Safe Credit']);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_VIEW);
        $this->actingAsReady($advisor);

        $this->from(route('advisor.cancellations.index'))
            ->get(route('advisor.cancellations.index', ['type' => 'CREDIT', 'plate' => 'ABC123']))
            ->assertRedirect(route('advisor.cancellations.index', absolute: false))
            ->assertSessionHasErrors(['plate']);

        $this->from(route('advisor.cancellations.index'))
            ->get(route('advisor.cancellations.index', ['type' => 'MOTO', 'credit_number' => '0001']))
            ->assertRedirect(route('advisor.cancellations.index', absolute: false))
            ->assertSessionHasErrors(['credit_number']);

        $this->from(route('advisor.cancellations.index'))
            ->get(route('advisor.cancellations.index', ['plate' => 'ABC123', 'credit_number' => '0001']))
            ->assertRedirect(route('advisor.cancellations.index', absolute: false))
            ->assertSessionHasErrors(['credit_number']);

        $this->from(route('advisor.cancellations.index'))
            ->get(route('advisor.cancellations.index', ['sort' => 'holder_name desc']))
            ->assertRedirect(route('advisor.cancellations.index', absolute: false))
            ->assertSessionHasErrors(['sort']);

        $this->get(route('advisor.cancellations.index', ['holder_name' => "' OR 1=1 --"]))
            ->assertOk()
            ->assertDontSee('Safe Moto')
            ->assertDontSee('Safe Credit')
            ->assertSee('No hay solicitudes');
    }

    public function test_pagination_and_sort_are_server_side_and_stable(): void
    {
        $advisor = $this->makeAdvisor('advisor-sort', 'Advisor Sort');
        $client = $this->makeClient('1000000007', 'Client Sort');
        $old = $this->makeMoto($client, $advisor, [
            'holder_name' => 'Old Row',
            'radicado' => 20003,
            'created_at' => '2026-08-17 08:00:00',
            'updated_at' => '2026-08-17 08:00:00',
        ]);
        $middle = $this->makeCredit($client, $advisor, [
            'holder_name' => 'Middle Row',
            'radicado' => 20002,
            'created_at' => '2026-08-18 08:00:00',
            'updated_at' => '2026-08-18 08:00:00',
        ]);
        $new = $this->makeMoto($client, $advisor, [
            'holder_name' => 'New Row',
            'radicado' => 20001,
            'created_at' => '2026-08-19 08:00:00',
            'updated_at' => '2026-08-19 08:00:00',
        ]);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_VIEW);
        $this->actingAsReady($advisor);

        $this->get(route('advisor.cancellations.index', ['per_page' => 2]))
            ->assertOk()
            ->assertSeeInOrder(['New Row', 'Middle Row'])
            ->assertDontSee('Old Row')
            ->assertSee('Siguiente');

        $this->get(route('advisor.cancellations.index', ['per_page' => 2, 'page' => 2]))
            ->assertOk()
            ->assertSee('Old Row')
            ->assertDontSee('New Row');

        $this->get(route('advisor.cancellations.index', ['sort' => 'radicado_asc']))
            ->assertOk()
            ->assertSeeInOrder(['New Row', 'Middle Row', 'Old Row']);

        $this->assertSame(20003, $old->radicado);
        $this->assertSame(20002, $middle->radicado);
        $this->assertSame(20001, $new->radicado);
    }

    public function test_action_visibility_follows_permissions_and_export_is_absent(): void
    {
        $advisor = $this->makeAdvisor('advisor-actions', 'Advisor Actions');
        $client = $this->makeClient('1000000008', 'Client Actions');
        $moto = $this->makeMoto($client, $advisor, ['holder_name' => 'Action Moto']);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_VIEW);
        $this->actingAsReady($advisor);

        $this->get(route('advisor.cancellations.index'))
            ->assertOk()
            ->assertDontSee('Editar')
            ->assertDontSee('Reasignar')
            ->assertDontSee('Reintentar SMS')
            ->assertDontSee('Importar respuestas')
            ->assertDontSee('Exportar');

        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_UPDATE);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_REASSIGN);
        $this->grant(RoleCode::ADVISOR, PermissionKey::RADICADO_SMS_RETRY);
        $this->grant(RoleCode::ADVISOR, PermissionKey::RESPONSES_IMPORT);

        $this->get(route('advisor.cancellations.index'))
            ->assertOk()
            ->assertSee('Editar')
            ->assertDontSee('Reasignar')
            ->assertDontSee('Reintentar SMS')
            ->assertSee('Importar respuestas')
            ->assertDontSee('Exportar');

        $this->createRadicadoSmsAttempt($moto, SmsAttemptStatus::FAILED);

        $this->get(route('advisor.cancellations.index'))
            ->assertOk()
            ->assertSee('Editar')
            ->assertDontSee('Reasignar')
            ->assertSee('Reintentar SMS')
            ->assertSee('Importar respuestas')
            ->assertDontSee('Exportar');
    }

    public function test_workspace_query_count_stays_reasonable_for_many_rows(): void
    {
        $advisor = $this->makeAdvisor('advisor-n1', 'Advisor N1');
        $client = $this->makeClient('1000000009', 'Client N1');

        for ($index = 0; $index < 8; $index++) {
            $this->makeMoto($client, $advisor, ['holder_name' => 'Moto N1 '.$index]);
            $this->makeCredit($client, $advisor, ['holder_name' => 'Credit N1 '.$index]);
        }

        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_VIEW);
        $this->actingAsReady($advisor);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('advisor.cancellations.index', ['per_page' => 15]))
            ->assertOk()
            ->assertSee('Moto N1 7')
            ->assertSee('Credit N1 7');

        $this->assertLessThanOrEqual(15, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $hidden
     */
    private function assertSearchShowsOnly(array $query, string $visible, array $hidden): void
    {
        $response = $this->get(route('advisor.cancellations.index', $query))->assertOk();
        $response->assertSee($visible);

        foreach ($hidden as $text) {
            $response->assertDontSee($text);
        }
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
        $timestamps = $this->extractTimestamps($overrides);

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

        $this->applyTimestamps('moto_cancellations', $moto->id, $timestamps);

        return $moto->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCredit(User $owner, User $creator, array $overrides = []): CreditCancellation
    {
        $timestamps = $this->extractTimestamps($overrides);

        /** @var CreditCancellation $credit */
        $credit = CreditCancellation::query()->create(array_merge([
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

        $this->applyTimestamps('credit_cancellations', $credit->id, $timestamps);

        return $credit->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{created_at?: string, updated_at?: string}
     */
    private function extractTimestamps(array &$overrides): array
    {
        $timestamps = [];

        foreach (['created_at', 'updated_at'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $timestamps[$key] = (string) $overrides[$key];
                unset($overrides[$key]);
            }
        }

        return $timestamps;
    }

    /**
     * @param  array{created_at?: string, updated_at?: string}  $timestamps
     */
    private function applyTimestamps(string $table, int $id, array $timestamps): void
    {
        if ($timestamps === []) {
            return;
        }

        DB::table($table)
            ->where('id', $id)
            ->update($timestamps);
    }

    private function makeOtpChallenge(OtpPurpose $purpose): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase10-'.bin2hex(random_bytes(8)),
            'purpose' => $purpose,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase10'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }

    private function createCreditResponse(CreditCancellation $credit, User $advisor): void
    {
        CancellationResponse::query()->create([
            'credit_cancellation_id' => $credit->id,
            'cancellation_date' => CarbonImmutable::parse('2026-08-18'),
            'observation' => 'Respuesta final',
            'created_by_user_id' => $advisor->id,
        ]);
    }

    private function createRadicadoSmsAttempt(MotoCancellation|CreditCancellation $cancellation, SmsAttemptStatus $status): void
    {
        SmsAttempt::query()->create([
            'moto_cancellation_id' => $cancellation instanceof MotoCancellation ? $cancellation->id : null,
            'credit_cancellation_id' => $cancellation instanceof CreditCancellation ? $cancellation->id : null,
            'purpose' => SmsPurpose::RADICADO,
            'status' => $status,
            'is_manual_retry' => false,
            'destination' => '+573001234567',
            'safe_error_code' => $status === SmsAttemptStatus::SENT ? null : 'TEST_PROVIDER_ERROR',
            'safe_error_message' => $status === SmsAttemptStatus::SENT ? null : 'Provider error.',
            'completed_at' => now(),
        ]);
    }

    private function actingAsReady(User $user): void
    {
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
                '_token' => 'phase10-token',
            ]);
    }
}
