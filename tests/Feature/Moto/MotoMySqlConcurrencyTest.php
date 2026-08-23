<?php

namespace Tests\Feature\Moto;

use App\Actions\Otp\IssueOtpChallengeAction;
use App\DTOs\Otp\IssueOtpChallengeData;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationType;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\OtpPurpose;
use App\Enums\SmsPurpose;
use App\Models\MotoCancellation;
use App\Models\RadicadoSequence;
use App\Models\SmsAttempt;
use App\Services\Sms\FakeSmsGateway;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class MotoMySqlConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useMySqlDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
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
            'property_lien_adeinco' => $index % 2 === 0,
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
}
