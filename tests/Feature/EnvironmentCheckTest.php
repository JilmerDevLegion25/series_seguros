<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

final class EnvironmentCheckTest extends TestCase
{
    public function test_environment_checker_reports_errors_without_mysql_runtime(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        config([
            'app.env' => 'production',
            'app.debug' => true,
            'database.default' => 'sqlite',
            'sms.driver' => 'fake',
        ]);

        $exitCode = Artisan::call('app:check-environment');
        $output = Artisan::output();

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('[ERROR] database.connection', $output);
        $this->assertStringContainsString('[ERROR] app.debug', $output);
        $this->assertStringContainsString('[ERROR] sms.driver', $output);
    }
}
