<?php

namespace Tests\Feature\Seeders;

use App\Enums\AuditEventType;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\Notification;
use App\Models\OtpChallenge;
use App\Models\Permission;
use App\Models\RadicadoSequence;
use App\Models\Response;
use App\Models\Role;
use App\Models\SmsAttempt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class DevelopmentSeederTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_development_seeder_is_skipped_in_production(): void
    {
        config(['app.env' => 'production']);

        Artisan::call('db:seed', ['--class' => DevelopmentSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, MotoCancellation::query()->count());
        $this->assertSame(0, CreditCancellation::query()->count());
    }

    public function test_migrate_fresh_seed_builds_the_development_dataset(): void
    {
        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        Artisan::call('db:seed', ['--class' => DevelopmentSeeder::class, '--force' => true]);

        $clientOne = User::query()->where('username', '1001234567')->firstOrFail();
        $clientTwo = User::query()->where('username', '1007654321')->firstOrFail();
        $advisorOne = User::query()->where('username', 'ADVISOR_DEMO_1')->firstOrFail();
        $advisorTwo = User::query()->where('username', 'ADVISOR_DEMO_2')->firstOrFail();

        $this->assertSame(Role::idFor(RoleCode::CLIENT), $clientOne->role_id);
        $this->assertSame(Role::idFor(RoleCode::CLIENT), $clientTwo->role_id);
        $this->assertSame(Role::idFor(RoleCode::ADVISOR), $advisorOne->role_id);
        $this->assertSame(Role::idFor(RoleCode::ADVISOR), $advisorTwo->role_id);
        $this->assertTrue(Hash::check(DevelopmentSeeder::CLIENT_PASSWORD, $clientOne->password));
        $this->assertTrue(Hash::check(DevelopmentSeeder::ADVISOR_PASSWORD, $advisorOne->password));

        $this->assertSame(2, User::query()->where('role_id', Role::idFor(RoleCode::CLIENT))->count());
        $this->assertSame(2, User::query()->where('role_id', Role::idFor(RoleCode::ADVISOR))->count());
        $this->assertFalse(Role::query()->where('code', 'ADMIN')->exists());
        $this->assertFalse(Schema::hasTable('user_permissions'));
        $this->assertSame(2, Role::query()->count());

        $expectedPermissions = array_map(
            static fn (PermissionKey $permissionKey): string => $permissionKey->value,
            PermissionKey::cases(),
        );
        $this->assertEqualsCanonicalizing($expectedPermissions, $this->permissionKeysFor($advisorOne));
        $this->assertEqualsCanonicalizing($expectedPermissions, $this->permissionKeysFor($advisorTwo));
        $this->assertSame($this->permissionKeysFor($advisorOne), $this->permissionKeysFor($advisorTwo));

        $this->assertSame(5, $this->cancellationsFor($clientOne));
        $this->assertSame(5, $this->cancellationsFor($clientTwo));
        $this->assertSame(5, MotoCancellation::query()->count());
        $this->assertSame(5, CreditCancellation::query()->count());
        $this->assertGreaterThan(0, MotoCancellation::query()->where('status', CancellationStatus::EN_GESTION)->count());
        $this->assertGreaterThan(0, MotoCancellation::query()->where('status', CancellationStatus::RESPUESTA_OBTENIDA)->count());
        $this->assertGreaterThan(0, CreditCancellation::query()->where('status', CancellationStatus::EN_GESTION)->count());
        $this->assertGreaterThan(0, CreditCancellation::query()->where('status', CancellationStatus::RESPUESTA_OBTENIDA)->count());

        $advisorIds = [$advisorOne->id, $advisorTwo->id];
        $this->assertSame(10, $this->assignedTo($advisorIds));
        $this->assertGreaterThan(0, $this->assignedTo([$advisorOne->id]));
        $this->assertGreaterThan(0, $this->assignedTo([$advisorTwo->id]));

        $terminalCount = MotoCancellation::query()->where('status', CancellationStatus::RESPUESTA_OBTENIDA)->count()
            + CreditCancellation::query()->where('status', CancellationStatus::RESPUESTA_OBTENIDA)->count();
        $this->assertSame($terminalCount, Response::query()->count());
        $this->assertSame($terminalCount, Notification::query()->count());
        $this->assertGreaterThan(0, Notification::query()->whereNull('read_at')->count());
        $this->assertGreaterThan(0, Notification::query()->whereNotNull('read_at')->count());

        $this->assertGreaterThanOrEqual(10, Activity::query()->count());
        $this->assertGreaterThan(Activity::query()->count(), Audit::query()->count());
        $this->assertFalse(Activity::query()->get()->contains(
            static fn (Activity $activity): bool => ($activity->metadata['event'] ?? null) === AuditEventType::RADICADO_SMS_RETRY_REQUESTED->value,
        ));
        $this->assertGreaterThan(0, SmsAttempt::query()->where('is_manual_retry', false)->count());
        $this->assertGreaterThan(0, SmsAttempt::query()->where('is_manual_retry', true)->count());

        $this->assertSame(1006, RadicadoSequence::query()->where('type', CancellationType::MOTO)->value('next_value'));
        $this->assertSame(2006, RadicadoSequence::query()->where('type', CancellationType::CREDIT)->value('next_value'));
        $this->assertSame(0, CreditCancellation::query()
            ->where('cancel_personal_accidents', false)
            ->where('cancel_unemployment_insurance', false)
            ->count());
        $this->assertSame(0, OtpChallenge::query()
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->count());
        $this->assertSame(0, OtpChallenge::query()->whereNotNull('encrypted_payload')->count());
        $this->assertTrue(OtpChallenge::query()->pluck('otp_mac')->every(
            static fn (string $mac): bool => strlen($mac) === 64 && preg_match('/^\d{6}$/', $mac) !== 1,
        ));

        $this->assertSame(4, User::query()->count());
        $this->assertSame(12, Permission::query()->count());
        $this->assertSame(5, MotoCancellation::query()->count());
        $this->assertSame(5, CreditCancellation::query()->count());
    }

    public function test_demo_users_can_login(): void
    {
        Artisan::call('db:seed', ['--class' => DevelopmentSeeder::class, '--force' => true]);

        foreach ([
            ['username' => '1001234567', 'password' => DevelopmentSeeder::CLIENT_PASSWORD],
            ['username' => '1007654321', 'password' => DevelopmentSeeder::CLIENT_PASSWORD],
            ['username' => 'advisor_demo_1', 'password' => DevelopmentSeeder::ADVISOR_PASSWORD],
            ['username' => 'advisor_demo_2', 'password' => DevelopmentSeeder::ADVISOR_PASSWORD],
        ] as $credentials) {
            $this->post('/login', $credentials)
                ->assertRedirect(route('dashboard', absolute: false));
            $this->assertAuthenticated();
            $this->post('/logout');
            $this->assertGuest();
        }
    }

    /**
     * @return list<string>
     */
    private function permissionKeysFor(User $user): array
    {
        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->orderBy('permissions.key')
            ->pluck('permissions.key')
            ->all();
    }

    private function cancellationsFor(User $client): int
    {
        return MotoCancellation::query()->where('owner_user_id', $client->id)->count()
            + CreditCancellation::query()->where('owner_user_id', $client->id)->count();
    }

    /**
     * @param  list<int>  $advisorIds
     */
    private function assignedTo(array $advisorIds): int
    {
        return MotoCancellation::query()->whereIn('assigned_advisor_user_id', $advisorIds)->count()
            + CreditCancellation::query()->whereIn('assigned_advisor_user_id', $advisorIds)->count();
    }
}
