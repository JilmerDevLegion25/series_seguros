<?php

namespace Tests\Feature\Moto;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Actions\Otp\IssueOtpChallengeAction;
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
use App\Enums\SmsPurpose;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Models\RadicadoSequence;
use App\Models\Role;
use App\Models\SmsAttempt;
use App\Models\User;
use App\Services\Clock\Clock;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MotoMySqlConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useMySqlDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
        config()->set('sms.driver', 'fake');
        $this->app->bind(SmsGateway::class, fn (): SmsGateway => new FakeSmsGateway(app(Clock::class)));
        FakeSmsGateway::clearSentMessages();
    }

    public function test_concurrent_public_completions_allocate_unique_radicados(): void
    {
        $this->seedMotoSequence(900);
        $challenges = [];

        for ($index = 0; $index < 4; $index++) {
            $challenges[] = $this->issueChallenge($index);
        }

        $startFile = $this->startFile('moto-radicado');
        $processes = [];

        foreach ($challenges as $index => $challenge) {
            $processes[] = $this->startChildProcess(
                $this->completeScript(),
                [$startFile, $challenge['reference'], $challenge['otp'], '10.30.0.'.($index + 1)],
            );
        }

        touch($startFile);
        $outputs = $this->waitForProcesses($processes);

        $radicados = MotoCancellation::query()->orderBy('radicado')->pluck('radicado')->all();

        $this->assertSame(['CREATED:900', 'CREATED:901', 'CREATED:902', 'CREATED:903'], $this->sortedCreatedOutputs($outputs), implode(' | ', $outputs));
        $this->assertSame([900, 901, 902, 903], $radicados);
        $this->assertSame(904, RadicadoSequence::query()->where('type', CancellationType::MOTO)->value('next_value'));
    }

    public function test_concurrent_double_completion_creates_one_moto(): void
    {
        $this->seedMotoSequence(1000);
        $challenge = $this->issueChallenge(7);
        $startFile = $this->startFile('moto-double');
        $processes = [
            $this->startChildProcess($this->completeScript(), [$startFile, $challenge['reference'], $challenge['otp'], '10.40.0.1']),
            $this->startChildProcess($this->completeScript(), [$startFile, $challenge['reference'], $challenge['otp'], '10.40.0.2']),
        ];

        touch($startFile);
        $outputs = $this->waitForProcesses($processes);
        sort($outputs);

        $this->assertSame(['CREATED:1000', 'EXISTING:1000'], $outputs, implode(' | ', $outputs));
        $this->assertSame(1, MotoCancellation::query()->count());
        $this->assertSame(1, SmsAttempt::query()->where('purpose', SmsPurpose::RADICADO)->count());
        $this->assertSame(1001, RadicadoSequence::query()->where('type', CancellationType::MOTO)->value('next_value'));
    }

    public function test_concurrent_generate_radicado_for_pending_moto_allocates_once(): void
    {
        $this->seedMotoSequence(1100);
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('7000000001');
        $moto = $this->makePendingLienMoto($owner, $advisor);
        $this->grantAdvisor(PermissionKey::CANCELLATIONS_UPDATE);

        $startFile = $this->startFile('moto-manual-radicado');
        $processes = [
            $this->startChildProcess($this->generateRadicadoScript(), [$startFile, (string) $moto->id, (string) $advisor->id]),
            $this->startChildProcess($this->generateRadicadoScript(), [$startFile, (string) $moto->id, (string) $advisor->id]),
        ];

        touch($startFile);
        $outputs = $this->waitForProcesses($processes);

        $moto->refresh();

        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => $output === 'RADICATED:1100')), implode(' | ', $outputs));
        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => $output === 'DENIED:RADICADO_ALREADY_EXISTS')), implode(' | ', $outputs));
        $this->assertSame(1100, $moto->radicado);
        $this->assertSame(CancellationStatus::EN_GESTION, $moto->status);
        $this->assertSame(2, $moto->version);
        $this->assertSame(1101, RadicadoSequence::query()->where('type', CancellationType::MOTO)->value('next_value'));
        $this->assertSame(1, SmsAttempt::query()->where('moto_cancellation_id', $moto->id)->where('purpose', SmsPurpose::RADICADO)->count());
        $this->assertSame(1, Activity::query()->where('type', ActivityType::RADICADO_GENERATED)->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::RADICADO_GENERATED)->where('moto_cancellation_id', $moto->id)->count());
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
     * @return array{reference: string, otp: string}
     */
    private function issueChallenge(int $index): array
    {
        $challenge = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+57300100000'.$index,
            payload: $this->payload($index),
        ))->challenge;

        return [
            'reference' => $challenge->public_reference,
            'otp' => $this->latestOtp(),
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function payload(int $index): array
    {
        return [
            'payload_schema' => 'moto_create_v1',
            'origin' => CancellationOrigin::PUBLIC->value,
            'created_by_user_id' => null,
            'holder_name' => 'Concurrent Client '.$index,
            'holder_cedula' => (string) (1000000000 + $index),
            'property_lien_adeinco' => false,
            'plate' => 'PLT'.$index,
            'holder_phone' => '+57300100000'.$index,
            'holder_email' => 'client'.$index.'@example.test',
            'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS->value,
            'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL->value,
            'is_credit_holder' => false,
            'credit_owner_name' => 'Credit Owner '.$index,
            'credit_owner_cedula' => (string) (2000000000 + $index),
            'ownership_declaration_accepted' => true,
            'data_processing_accepted' => true,
        ];
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
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$name.'-'.bin2hex(random_bytes(8)).'.start';

        if (file_exists($path)) {
            unlink($path);
        }

        return $path;
    }

    /**
     * @param  list<string>  $outputs
     * @return list<string>
     */
    private function sortedCreatedOutputs(array $outputs): array
    {
        $created = array_values(array_filter($outputs, static fn (string $output): bool => str_starts_with($output, 'CREATED:')));
        sort($created);

        return $created;
    }

    private function completeScript(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! file_exists($argv[1])) {
    usleep(10000);
}
try {
    $result = $app->make(App\Actions\Cancellations\Moto\CompleteMotoCancellationAction::class)->execute(
        $argv[2],
        $argv[3],
        $argv[4],
        null,
        App\Enums\CancellationOrigin::PUBLIC,
        'mysql-concurrency-test'
    );
    if (! $result->completed || $result->moto === null) {
        echo 'FAILED:'.$result->failureReason;
        return;
    }
    echo ($result->created ? 'CREATED:' : 'EXISTING:').$result->moto->radicado;
} catch (Throwable $throwable) {
    echo 'EXCEPTION:'.get_class($throwable).':'.$throwable->getMessage();
}
PHP;
    }

    private function generateRadicadoScript(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! file_exists($argv[1])) {
    usleep(10000);
}
try {
    $moto = $app->make(App\Actions\Cancellations\Moto\GenerateMotoRadicadoAction::class)->execute(
        App\Models\MotoCancellation::query()->findOrFail((int) $argv[2]),
        App\Models\User::query()->findOrFail((int) $argv[3]),
        'phase126-mysql-concurrency'
    );
    echo 'RADICATED:'.$moto->radicado;
} catch (App\Exceptions\CancellationMutationException $exception) {
    echo 'DENIED:'.$exception->getMessage();
} catch (Throwable $throwable) {
    echo 'EXCEPTION:'.get_class($throwable).':'.$throwable->getMessage();
}
PHP;
    }

    private function latestOtp(): string
    {
        $messages = FakeSmsGateway::sentMessages();
        $message = end($messages);

        $this->assertIsArray($message);
        $this->assertSame(1, preg_match('/\b(\d{6})\b/', $message['message'], $matches));

        return $matches[1];
    }

    private function seedMotoSequence(int $nextValue): void
    {
        RadicadoSequence::query()->create([
            'type' => CancellationType::MOTO,
            'next_value' => $nextValue,
        ]);
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

    private function makePendingLienMoto(User $owner, User $creator): MotoCancellation
    {
        return MotoCancellation::query()->create([
            'otp_challenge_id' => $this->makeConsumedChallenge()->id,
            'radicado' => null,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
            'assigned_advisor_user_id' => $creator->id,
            'origin' => CancellationOrigin::ADVISOR,
            'status' => CancellationStatus::PENDIENTE_RADICACION,
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

    private function makeConsumedChallenge(): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase126-mysql-'.bin2hex(random_bytes(8)),
            'purpose' => OtpPurpose::CREATE_MOTO,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase126-mysql'),
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
