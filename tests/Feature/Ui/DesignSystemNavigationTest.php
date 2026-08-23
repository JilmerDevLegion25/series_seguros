<?php

namespace Tests\Feature\Ui;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\OtpPurpose;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class DesignSystemNavigationTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_guest_and_public_pages_use_local_design_assets_without_cdn_or_inline_styles(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('assets/app.css')
            ->assertSee('assets/app.js')
            ->assertSee('auth-card')
            ->assertDontSee('<style', false)
            ->assertDontSee('cdn.', false)
            ->assertDontSee('unpkg', false);

        $this->get(route('public.moto.create'))
            ->assertOk()
            ->assertSee('form-section')
            ->assertSee('Cancelacion Moto')
            ->assertDontSee('<style', false);

        $this->get(route('public.credit.create'))
            ->assertOk()
            ->assertSee('form-section')
            ->assertSee('Cancelacion Credit')
            ->assertDontSee('<style', false);
    }

    public function test_advisor_sidebar_is_permission_aware_and_has_desktop_mobile_controls(): void
    {
        $advisor = $this->makeAdvisor();
        $this->actingAsReady($advisor);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('advisor-sidebar')
            ->assertSee('data-sidebar-toggle', false)
            ->assertSee('data-sidebar-overlay', false)
            ->assertSee('Dashboard')
            ->assertDontSee('Solicitudes')
            ->assertDontSee('Crear Moto')
            ->assertDontSee('Importar respuestas')
            ->assertDontSee('Auditoria')
            ->assertDontSee('Asesores')
            ->assertDontSee('Exportar');

        foreach ([
            PermissionKey::CANCELLATIONS_VIEW,
            PermissionKey::CANCELLATIONS_CREATE,
            PermissionKey::RESPONSES_IMPORT,
            PermissionKey::CANCELLATIONS_EXPORT,
            PermissionKey::CANCELLATIONS_ACTIVITY_VIEW,
            PermissionKey::ADVISOR_ACCOUNTS_VIEW,
        ] as $permission) {
            $this->grant(RoleCode::ADVISOR, $permission);
        }

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Solicitudes')
            ->assertSee('Crear Moto')
            ->assertSee('Crear Credito')
            ->assertSee('Importar respuestas')
            ->assertSee('Reportes')
            ->assertDontSee('Auditoria')
            ->assertSee('Asesores');
    }

    public function test_client_layout_is_simplified_and_excludes_advisor_shell(): void
    {
        $client = $this->makeClient();
        $this->actingAsReady($client);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('client-shell')
            ->assertSee('Portal Client')
            ->assertSee('Panel Client')
            ->assertSee('assets/app.css')
            ->assertDontSee('advisor-sidebar')
            ->assertDontSee('data-sidebar-toggle', false)
            ->assertDontSee('Importar respuestas')
            ->assertDontSee('Cuentas Advisor')
            ->assertDontSee('localStorage');
    }

    public function test_forbidden_page_guides_authenticated_user_back_to_dashboard(): void
    {
        $client = $this->makeClient('1000000022', 'Client Forbidden');
        $this->actingAsReady($client);

        $this->get(route('advisor.cancellations.index'))
            ->assertForbidden()
            ->assertSee('forbidden-page', false)
            ->assertSee('403')
            ->assertSee('Acceso restringido')
            ->assertSee('Volver al menu')
            ->assertSee(route('dashboard', absolute: false), false);
    }

    public function test_workspace_has_filter_drawer_structure_and_mobile_cards_without_new_routes(): void
    {
        $advisor = $this->makeAdvisor();
        $client = $this->makeClient('1000000010', 'Client UI');
        $this->makeMoto($client, $advisor);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_VIEW);
        $this->actingAsReady($advisor);

        $this->get(route('advisor.cancellations.index'))
            ->assertOk()
            ->assertSee('Workspace Asesor')
            ->assertSee('data-filter-toggle', false)
            ->assertSee('data-filter-panel', false)
            ->assertSee('mobile-card-list')
            ->assertSee('Client UI')
            ->assertDontSee('Phase 13');
    }

    private function makeAdvisor(string $username = 'ADVISORUI'): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::ADVISOR),
            'username' => $username,
            'identity' => null,
            'name' => 'Advisor UI',
            'email' => 'advisor-ui@example.test',
            'phone' => '+573009999999',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function makeClient(string $identity = '1000000001', string $name = 'Client UI'): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => $identity,
            'identity' => $identity,
            'name' => $name,
            'email' => 'client-ui@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function makeMoto(User $owner, User $creator): MotoCancellation
    {
        return MotoCancellation::query()->create([
            'otp_challenge_id' => $this->makeOtpChallenge()->id,
            'radicado' => 65000,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
            'assigned_advisor_user_id' => $creator->id,
            'origin' => CancellationOrigin::ADVISOR,
            'status' => CancellationStatus::EN_GESTION,
            'version' => 1,
            'holder_name' => $owner->name,
            'holder_cedula' => (string) $owner->identity,
            'property_lien_adeinco' => true,
            'plate' => 'UI123',
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

    private function makeOtpChallenge(): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => bin2hex(random_bytes(16)),
            'purpose' => OtpPurpose::CREATE_MOTO,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash_hmac('sha256', '000000', 'ui-test-key'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }

    private function grant(RoleCode $roleCode, PermissionKey $permissionKey): void
    {
        app(GrantRolePermissionAction::class)->execute(
            Role::query()->where('code', $roleCode->value)->firstOrFail(),
            $permissionKey,
        );
    }

    private function actingAsReady(User $user): void
    {
        $this->actingAs($user);
        session()->put('auth_started_at', now()->getTimestamp());
        session()->put('auth_last_activity_at', now()->getTimestamp());
    }
}
