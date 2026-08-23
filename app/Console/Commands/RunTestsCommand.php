<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class RunTestsCommand extends Command
{
    protected $signature = 'test {--filter=} {--testsuite=}';

    protected $description = 'Run the PHPUnit test suite.';

    public function handle(): int
    {
        $phpunit = base_path('vendor/bin/phpunit');

        if (! is_file($phpunit)) {
            $this->error('PHPUnit binary was not found. Run composer install first.');

            return self::FAILURE;
        }

        $arguments = [PHP_BINARY, $phpunit];

        if (is_string($this->option('filter')) && $this->option('filter') !== '') {
            $arguments[] = '--filter';
            $arguments[] = $this->option('filter');
        }

        if (is_string($this->option('testsuite')) && $this->option('testsuite') !== '') {
            $arguments[] = '--testsuite';
            $arguments[] = $this->option('testsuite');
        }

        $process = new Process($arguments, base_path(), $this->testingEnvironment());
        $process->setTimeout(null);

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->getExitCode() ?? self::FAILURE;
    }

    /**
     * @return array<string, string>
     */
    private function testingEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'APP_DEBUG' => 'false',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'SESSION_DRIVER' => 'array',
            'SMS_DRIVER' => 'fake',
            'BCRYPT_ROUNDS' => '4',
        ];
    }
}
