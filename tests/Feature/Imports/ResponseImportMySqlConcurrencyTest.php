<?php

namespace Tests\Feature\Imports;

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
use App\Models\ImportRowResult;
use App\Models\MotoCancellation;
use App\Models\Notification;
use App\Models\OtpChallenge;
use App\Models\Response as CancellationResponse;
use App\Models\Role;
use App\Models\User;
use App\Services\Spreadsheet\OpenSpoutCancellationExportWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ResponseImportMySqlConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useMySqlDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
        Storage::disk('imports')->deleteDirectory('responses');
    }

    public function test_concurrent_response_imports_create_only_one_response(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('2000000001');
        $moto = $this->makeMoto($owner, $advisor);
        $this->grantAdvisor(PermissionKey::RESPONSES_IMPORT);
        $storedPaths = [
            $this->writeStoredImport($moto->radicado, 'concurrent-a'),
            $this->writeStoredImport($moto->radicado, 'concurrent-b'),
        ];
        $startFile = $this->startFile('response-import');
        $processes = [
            $this->startChildProcess($this->importScript(), [$startFile, $storedPaths[0], (string) $advisor->id]),
            $this->startChildProcess($this->importScript(), [$startFile, $storedPaths[1], (string) $advisor->id]),
        ];

        touch($startFile);
        $outputs = $this->waitForProcesses($processes);

        $moto->refresh();

        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => str_contains($output, 'SUCCESS:OK'))), implode(' | ', $outputs));
        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => str_contains($output, 'REJECTED:ALREADY_RESPONDED'))), implode(' | ', $outputs));
        $this->assertSame(CancellationStatus::RESPUESTA_OBTENIDA, $moto->status);
        $this->assertSame(2, $moto->version);
        $this->assertSame(1, CancellationResponse::query()->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Notification::query()->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Activity::query()->where('type', 'RESPONSE_OBTAINED')->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Audit::query()->where('event_type', 'RESPONSE_OBTAINED')->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, ImportRowResult::query()->where('status', 'SUCCESS')->count());
        $this->assertSame(1, ImportRowResult::query()->where('reason', 'ALREADY_RESPONDED')->count());
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

    private function writeStoredImport(int $radicado, string $name): string
    {
        $storedPath = 'responses/'.$name.'-'.bin2hex(random_bytes(8)).'.xlsx';
        $path = Storage::disk('imports')->path($storedPath);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        (new OpenSpoutCancellationExportWriter)->write($path, [
            ['Radicado', 'Fecha cancelacion', 'Observaciones'],
            [$radicado, now()->subDay()->toDateTimeImmutable(), 'Respuesta concurrente'],
        ]);

        return $storedPath;
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
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'phase08-'.$name.'-'.bin2hex(random_bytes(8)).'.start';

        if (file_exists($path)) {
            unlink($path);
        }

        return $path;
    }

    private function importScript(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! file_exists($argv[1])) {
    usleep(10000);
}
try {
    $result = $app->make(App\Actions\Imports\ProcessResponseImportAction::class)->execute(
        new App\DTOs\Imports\ResponseImportData(App\Enums\CancellationType::MOTO, $argv[2]),
        App\Models\User::query()->findOrFail((int) $argv[3]),
        'phase08-mysql-concurrency'
    );
    $row = App\Models\ImportRowResult::query()
        ->where('import_batch_id', $result->batch->id)
        ->firstOrFail();
    echo $row->status->value.':'.($row->reason?->value ?? 'OK').':'.$result->batch->id;
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
            'otp_challenge_id' => $this->makeOtpChallenge()->id,
            'radicado' => 9100,
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

    private function makeOtpChallenge(): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase08-mysql-'.bin2hex(random_bytes(8)),
            'purpose' => OtpPurpose::CREATE_MOTO,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase08-mysql'),
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
