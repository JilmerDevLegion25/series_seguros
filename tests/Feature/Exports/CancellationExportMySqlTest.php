<?php

namespace Tests\Feature\Exports;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Actions\Exports\GenerateCancellationExportAction;
use App\DTOs\Cancellations\CancellationSearchFilters;
use App\Enums\AuditEventType;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\OtpPurpose;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Audit;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CancellationExportMySqlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useMySqlDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
        Storage::disk('exports')->deleteDirectory('cancellations');
    }

    public function test_mysql_export_allows_global_audit_without_activity_parent(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('3000001001');
        $this->makeMoto($owner, $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_EXPORT);

        $result = app(GenerateCancellationExportAction::class)->execute(
            new CancellationSearchFilters(type: CancellationType::MOTO),
            $advisor,
            'phase11-mysql-export',
        );

        $audit = Audit::query()->where('event_type', AuditEventType::EXPORT_GENERATED)->firstOrFail();

        $this->assertSame(1, $result->rowCount);
        $this->assertTrue(Storage::disk('exports')->exists($result->storedPath));
        $this->assertNull($audit->moto_cancellation_id);
        $this->assertNull($audit->credit_cancellation_id);
        $this->assertSame('phase11-mysql-export', $audit->request_id);
    }

    private function useMySqlDatabase(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.host', 'db');
        config()->set('database.connections.mysql.database', 'cancelacion_series');
        config()->set('database.connections.mysql.username', 'cancelacion_series');
        config()->set('database.connections.mysql.password', 'local_dev_password');
        config()->set('session.driver', 'database');

        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
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
            'otp_challenge_id' => $this->makeOtpChallenge()->id,
            'radicado' => 12000,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
            'assigned_advisor_user_id' => $creator->id,
            'origin' => CancellationOrigin::ADVISOR,
            'status' => CancellationStatus::EN_GESTION,
            'version' => 1,
            'holder_name' => $owner->name,
            'holder_cedula' => (string) $owner->identity,
            'property_lien_adeinco' => true,
            'plate' => 'MYSQL11',
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
            'public_reference' => 'phase11-mysql-'.bin2hex(random_bytes(8)),
            'purpose' => OtpPurpose::CREATE_MOTO,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase11-mysql'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }
}
