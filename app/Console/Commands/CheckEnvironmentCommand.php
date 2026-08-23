<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CheckEnvironmentCommand extends Command
{
    protected $signature = 'app:check-environment';

    protected $description = 'Validate the Phase 00 runtime and deployment-sensitive configuration.';

    /**
     * @var list<array{level: string, check: string, message: string}>
     */
    private array $results = [];

    public function handle(): int
    {
        $this->results = [];

        $this->checkPhpVersion();
        $this->checkExtensions();
        $this->checkAppKey();
        $this->checkOtpMacKey();
        $this->checkDatabase();
        $this->checkWritablePaths();
        $this->checkProductionDebug();
        $this->checkSessionSecurity();
        $this->checkSmsDriver();

        foreach ($this->results as $result) {
            $this->line(sprintf('[%s] %s: %s', $result['level'], $result['check'], $result['message']));
        }

        return $this->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    private function checkPhpVersion(): void
    {
        $version = PHP_VERSION;

        if (version_compare($version, '8.3.0', '>=') && version_compare($version, '8.4.0', '<')) {
            $this->infoResult('php.version', "PHP {$version}");

            return;
        }

        $this->errorResult('php.version', "PHP 8.3 required; current runtime is {$version}");
    }

    private function checkExtensions(): void
    {
        $required = [
            'ctype',
            'curl',
            'dom',
            'fileinfo',
            'filter',
            'hash',
            'mbstring',
            'openssl',
            'pdo',
            'pdo_mysql',
            'session',
            'tokenizer',
            'xml',
            'xmlreader',
            'xmlwriter',
            'zip',
        ];

        $missing = array_values(array_filter(
            $required,
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));

        if ($missing === []) {
            $this->infoResult('php.extensions', 'Critical extensions are loaded');

            return;
        }

        $this->errorResult('php.extensions', 'Missing: '.implode(', ', $missing));
    }

    private function checkAppKey(): void
    {
        $key = (string) config('app.key', '');
        $localDummyKey = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

        if ($key === '' || $key === 'base64:replace-with-production-secret') {
            $this->errorResult('app.key', 'APP_KEY must be provided outside the repository');

            return;
        }

        if (app()->environment('production') && $key === $localDummyKey) {
            $this->errorResult('app.key', 'Development APP_KEY placeholder is forbidden in production');

            return;
        }

        $this->infoResult('app.key', 'APP_KEY is configured');
    }

    private function checkDatabase(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->errorResult('database.connection', 'MySQL connection is required for contractual runtime');

            return;
        }

        try {
            $version = (string) DB::selectOne('select version() as version')->version;
        } catch (Throwable $throwable) {
            $this->errorResult('database.connection', 'Unable to connect to database: '.$throwable->getCode());

            return;
        }

        if (str_starts_with($version, '8.0.')) {
            $this->infoResult('database.mysql_version', "MySQL {$version}");

            return;
        }

        $this->errorResult('database.mysql_version', "MySQL 8.0 required; server reported {$version}");
    }

    private function checkOtpMacKey(): void
    {
        $key = (string) config('otp.mac_key', '');

        if ($key === '') {
            $this->errorResult('otp.mac_key', 'OTP_MAC_KEY must be provided outside the database');

            return;
        }

        if (app()->environment('production') && str_contains($key, 'not-secret')) {
            $this->errorResult('otp.mac_key', 'Development OTP_MAC_KEY placeholder is forbidden in production');

            return;
        }

        $this->infoResult('otp.mac_key', 'OTP MAC key is configured');
    }

    private function checkWritablePaths(): void
    {
        foreach ([storage_path(), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $this->errorResult('filesystem.writable', "{$path} is not writable");

                continue;
            }

            $this->infoResult('filesystem.writable', "{$path} is writable");
        }
    }

    private function checkProductionDebug(): void
    {
        if (! app()->environment('production')) {
            $this->infoResult('app.debug', 'Production debug check not applicable outside production');

            return;
        }

        if ((bool) config('app.debug') === false) {
            $this->infoResult('app.debug', 'APP_DEBUG is disabled in production');

            return;
        }

        $this->errorResult('app.debug', 'APP_DEBUG must be false in production');
    }

    private function checkSessionSecurity(): void
    {
        if (! app()->environment('production')) {
            $this->infoResult('session.cookies', 'Production cookie check not applicable outside production');

            return;
        }

        if (config('session.secure') !== true) {
            $this->errorResult('session.cookies', 'Secure cookies must be enabled in production');
        }

        if (config('session.http_only') !== true) {
            $this->errorResult('session.cookies', 'HttpOnly cookies must be enabled in production');
        }

        if (config('session.same_site') !== 'lax') {
            $this->errorResult('session.cookies', 'SameSite=Lax is required in production');
        }

        if (! $this->hasCheckErrors('session.cookies')) {
            $this->infoResult('session.cookies', 'Secure, HttpOnly, SameSite=Lax are configured');
        }
    }

    private function checkSmsDriver(): void
    {
        $driver = (string) config('sms.driver');
        $allowed = config('sms.allowed_drivers', []);

        if (! is_array($allowed) || ! in_array($driver, $allowed, true)) {
            $this->errorResult('sms.driver', 'SMS driver is not allowlisted');

            return;
        }

        if (app()->environment('production') && $driver === 'fake') {
            $this->errorResult('sms.driver', 'Fake SMS gateway is forbidden in production');

            return;
        }

        if ($driver === 'provider') {
            $missing = [];

            foreach ([
                'endpoint' => config('sms.provider.endpoint'),
                'authorization' => config('sms.provider.authorization'),
                'from' => config('sms.provider.from'),
            ] as $key => $value) {
                if (! is_string($value) || trim($value) === '') {
                    $missing[] = $key;
                }
            }

            if ($missing !== []) {
                $this->errorResult('sms.provider', 'Provider SMS config is incomplete: '.implode(', ', $missing));

                return;
            }

            $this->infoResult('sms.driver', 'SMS provider adapter is configured');

            return;
        }

        $this->infoResult('sms.driver', "SMS driver {$driver} is valid for this environment");
    }

    private function hasErrors(): bool
    {
        foreach ($this->results as $result) {
            if ($result['level'] === 'ERROR') {
                return true;
            }
        }

        return false;
    }

    private function hasCheckErrors(string $check): bool
    {
        foreach ($this->results as $result) {
            if ($result['check'] === $check && $result['level'] === 'ERROR') {
                return true;
            }
        }

        return false;
    }

    private function infoResult(string $check, string $message): void
    {
        $this->results[] = ['level' => 'INFO', 'check' => $check, 'message' => $message];
    }

    private function errorResult(string $check, string $message): void
    {
        $this->results[] = ['level' => 'ERROR', 'check' => $check, 'message' => $message];
    }
}
