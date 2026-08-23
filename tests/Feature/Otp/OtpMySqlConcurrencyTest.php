<?php

namespace Tests\Feature\Otp;

use App\Actions\Otp\IssueOtpChallengeAction;
use App\DTOs\Otp\IssueOtpChallengeData;
use App\Enums\OtpPurpose;
use App\Services\Sms\FakeSmsGateway;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class OtpMySqlConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useMySqlDatabase();
        Artisan::call('migrate:fresh', ['--force' => true]);
        FakeSmsGateway::clearSentMessages();
    }

    public function test_concurrent_resend_does_not_exceed_three_emissions(): void
    {
        $challenge = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_MOTO,
            destination: '+573001234567',
        ))->challenge;
        $challenge->forceFill([
            'emission_count' => 2,
            'last_emitted_at' => now()->subSeconds(61),
        ])->save();

        $startFile = $this->startFile('resend');
        $processes = [
            $this->startChildProcess($this->resendScript(), [$startFile, $challenge->public_reference, '10.10.0.1']),
            $this->startChildProcess($this->resendScript(), [$startFile, $challenge->public_reference, '10.10.0.2']),
        ];

        touch($startFile);
        $outputs = $this->waitForProcesses($processes);

        $challenge->refresh();

        $this->assertSame(3, $challenge->emission_count);
        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => str_contains($output, 'RESENT'))));
        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => str_contains($output, 'DENIED'))));
    }

    public function test_concurrent_verify_and_consume_allows_single_consumption(): void
    {
        $challenge = app(IssueOtpChallengeAction::class)->execute(new IssueOtpChallengeData(
            purpose: OtpPurpose::CREATE_CREDIT,
            destination: '+573001234567',
            payload: ['identity' => '1234567890'],
        ))->challenge;
        $otp = $this->latestOtp();

        $startFile = $this->startFile('verify');
        $processes = [
            $this->startChildProcess($this->consumeScript(), [$startFile, $challenge->public_reference, $otp, '10.20.0.1']),
            $this->startChildProcess($this->consumeScript(), [$startFile, $challenge->public_reference, $otp, '10.20.0.2']),
        ];

        touch($startFile);
        $outputs = $this->waitForProcesses($processes);

        $challenge->refresh();

        $this->assertTrue($challenge->isConsumed(), implode(' | ', $outputs));
        $this->assertNull($challenge->encrypted_payload);
        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => $output === 'VALID')), implode(' | ', $outputs));
        $this->assertSame(1, count(array_filter($outputs, static fn (string $output): bool => str_starts_with($output, 'INVALID'))), implode(' | ', $outputs));
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
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'otp-'.$name.'-'.bin2hex(random_bytes(8)).'.start';

        if (file_exists($path)) {
            unlink($path);
        }

        return $path;
    }

    private function resendScript(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! file_exists($argv[1])) {
    usleep(10000);
}
try {
    $app->make(App\Actions\Otp\ResendOtpChallengeAction::class)->execute($argv[2], $argv[3]);
    echo 'RESENT';
} catch (Throwable) {
    echo 'DENIED';
}
PHP;
    }

    private function consumeScript(): string
    {
        return <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
while (! file_exists($argv[1])) {
    usleep(10000);
}
try {
    $result = $app->make(App\Actions\Otp\VerifyAndConsumeOtpChallengeAction::class)->execute($argv[2], $argv[3], $argv[4]);
    echo $result->valid ? 'VALID' : 'INVALID:'.$result->reason;
} catch (Throwable $throwable) {
    echo 'INVALID:'.get_class($throwable).':'.$throwable->getMessage();
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
}
