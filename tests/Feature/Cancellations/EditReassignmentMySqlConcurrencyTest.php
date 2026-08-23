<?php

namespace Tests\Feature\Cancellations;

use App\Actions\Authorization\GrantRolePermissionAction;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class EditReassignmentMySqlConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useMySqlDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_concurrent_moto_edits_allow_only_one_version_winner(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('3333333333');
        $moto = $this->makeMoto($owner, $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);

        $startFile = $this->startFile('moto-edit');
        $processes = [
            $this->startChildProcess($this->motoUpdateScript(), [$startFile, (string) $advisor->id, (string) $moto->id, 'WIN001']),
            $this->startChildProcess($this->motoUpdateScript(), [$startFile, (string) $advisor->id, (string) $moto->id, 'WIN002']),
        ];

        touch($startFile);
        $outputs = $this->waitForProcesses($processes);

        $moto->refresh();

        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => str_starts_with($output, 'UPDATED:'))), implode(' | ', $outputs));
        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => $output === 'FAILED:STALE_VERSION')), implode(' | ', $outputs));
        $this->assertSame(2, $moto->version);
        $this->assertContains($moto->plate, ['WIN001', 'WIN002']);
        $this->assertSame(1, Activity::query()->where('type', 'UPDATED')->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Audit::query()->where('event_type', 'CANCELLATION_UPDATED')->where('moto_cancellation_id', $moto->id)->count());
    }

    public function test_concurrent_credit_edits_allow_only_one_version_winner(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('4444444444');
        $credit = $this->makeCredit($owner, $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);

        $startFile = $this->startFile('credit-edit');
        $processes = [
            $this->startChildProcess($this->creditUpdateScript(), [$startFile, (string) $advisor->id, (string) $credit->id, '900001']),
            $this->startChildProcess($this->creditUpdateScript(), [$startFile, (string) $advisor->id, (string) $credit->id, '900002']),
        ];

        touch($startFile);
        $outputs = $this->waitForProcesses($processes);

        $credit->refresh();

        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => str_starts_with($output, 'UPDATED:'))), implode(' | ', $outputs));
        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => $output === 'FAILED:STALE_VERSION')), implode(' | ', $outputs));
        $this->assertSame(2, $credit->version);
        $this->assertContains($credit->credit_number, ['900001', '900002']);
        $this->assertSame(1, Activity::query()->where('type', 'UPDATED')->where('credit_cancellation_id', $credit->id)->count());
        $this->assertSame(1, Audit::query()->where('event_type', 'CANCELLATION_UPDATED')->where('credit_cancellation_id', $credit->id)->count());
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

    /**
     * @param  list<string>  $arguments
     */
    private function startChildProcess(string $script, array $arguments): Process
    {
        $process = new Process(array_merge([PHP_BINARY, '-r', $script], $arguments), base_path(), [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'APP_DEBUG' => 'false',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => 'db',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'cancelacion_series',
            'DB_USERNAME' => 'cancelacion_series',
            'DB_PASSWORD' => 'local_dev_password',
            'OTP_MAC_KEY' => (string) config('otp.mac_key'),
            'SESSION_DRIVER' => 'database',
            'SMS_DRIVER' => 'fake',
            'BCRYPT_ROUNDS' => '4',
        ]);
        $process->setTimeout(30);
        $process->start();

        return $process;
    }

    /**
     * @param  list<Process>  $processes
     * @return list<string>
     */
    private function waitForProcesses(array $processes): array
    {
        $outputs = [];

        foreach ($processes as $process) {
            $process->wait();
            $outputs[] = trim($process->getOutput().$process->getErrorOutput());
        }

        return $outputs;
    }

    private function startFile(string $name): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'phase06-'.$name.'-'.bin2hex(random_bytes(8)).'.start';

        if (file_exists($path)) {
            unlink($path);
        }

        return $path;
    }

    private function motoUpdateScript(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! file_exists($argv[1])) {
    usleep(10000);
}
try {
    $result = $app->make(App\Actions\Cancellations\Moto\UpdateMotoCancellationAction::class)->execute(
        App\Models\MotoCancellation::query()->findOrFail((int) $argv[3]),
        new App\DTOs\Cancellations\Moto\UpdateMotoCancellationData(1, 'Concurrent edit reason', ['plate' => $argv[4]]),
        App\Models\User::query()->findOrFail((int) $argv[2]),
        'phase06-moto-concurrency'
    );
    echo $result->changed ? 'UPDATED:'.$result->cancellation->version.':'.$result->cancellation->plate : 'NOOP';
} catch (App\Exceptions\CancellationMutationException $exception) {
    echo 'FAILED:'.$exception->getMessage();
} catch (Throwable $throwable) {
    echo 'EXCEPTION:'.get_class($throwable).':'.$throwable->getMessage();
}
PHP;
    }

    private function creditUpdateScript(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! file_exists($argv[1])) {
    usleep(10000);
}
try {
    $result = $app->make(App\Actions\Cancellations\Credit\UpdateCreditCancellationAction::class)->execute(
        App\Models\CreditCancellation::query()->findOrFail((int) $argv[3]),
        new App\DTOs\Cancellations\Credit\UpdateCreditCancellationData(1, 'Concurrent edit reason', ['credit_number' => $argv[4]]),
        App\Models\User::query()->findOrFail((int) $argv[2]),
        'phase06-credit-concurrency'
    );
    echo $result->changed ? 'UPDATED:'.$result->cancellation->version.':'.$result->cancellation->credit_number : 'NOOP';
} catch (App\Exceptions\CancellationMutationException $exception) {
    echo 'FAILED:'.$exception->getMessage();
} catch (Throwable $throwable) {
    echo 'EXCEPTION:'.get_class($throwable).':'.$throwable->getMessage();
}
PHP;
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
            'radicado' => 8000,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
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
            'radicado' => 9000,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
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
            'public_reference' => 'phase06-mysql-'.bin2hex(random_bytes(8)),
            'purpose' => $purpose,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase06-mysql'),
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
