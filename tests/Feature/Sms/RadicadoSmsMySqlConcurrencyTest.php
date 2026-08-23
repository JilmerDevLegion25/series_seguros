<?php

namespace Tests\Feature\Sms;

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
use App\Models\Activity;
use App\Models\Audit;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\SmsAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class RadicadoSmsMySqlConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useMySqlDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_concurrent_manual_retries_create_only_one_attempt_during_cooldown(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('5555555555');
        $moto = $this->makeMoto($owner, $advisor);
        $this->createInitialAttempt($moto);
        $this->grantAdvisor(PermissionKey::RADICADO_SMS_RETRY);

        $startFile = $this->startFile('radicado-sms-retry');
        $processes = [
            $this->startChildProcess($this->retryScript(), [$startFile, (string) $advisor->id, (string) $moto->id]),
            $this->startChildProcess($this->retryScript(), [$startFile, (string) $advisor->id, (string) $moto->id]),
        ];

        touch($startFile);
        $outputs = $this->waitForProcesses($processes);

        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => str_starts_with($output, 'RETRIED:'))), implode(' | ', $outputs));
        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => $output === 'DENIED:SMS_RETRY_COOLDOWN')), implode(' | ', $outputs));
        $this->assertSame(1, SmsAttempt::query()->where('moto_cancellation_id', $moto->id)->where('is_manual_retry', true)->count());
        $this->assertSame(1, Audit::query()->where('event_type', 'RADICADO_SMS_RETRY_REQUESTED')->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(0, Activity::query()->count());
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
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'phase07-'.$name.'-'.bin2hex(random_bytes(8)).'.start';

        if (file_exists($path)) {
            unlink($path);
        }

        return $path;
    }

    private function retryScript(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! file_exists($argv[1])) {
    usleep(10000);
}
try {
    $attempt = $app->make(App\Actions\Sms\RetryRadicadoSmsAction::class)->retryMoto(
        App\Models\MotoCancellation::query()->findOrFail((int) $argv[3]),
        App\Models\User::query()->findOrFail((int) $argv[2]),
        'phase07-mysql-concurrency'
    );
    echo 'RETRIED:'.$attempt->id.':'.$attempt->status->value;
} catch (App\Exceptions\SmsRetryException $exception) {
    echo 'DENIED:'.$exception->getMessage();
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
            'radicado' => 8700,
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
            'public_reference' => 'phase07-mysql-'.bin2hex(random_bytes(8)),
            'purpose' => OtpPurpose::CREATE_MOTO,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase07-mysql'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }

    private function createInitialAttempt(MotoCancellation $moto): SmsAttempt
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
}
