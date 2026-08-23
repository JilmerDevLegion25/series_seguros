<?php

namespace App\Providers;

use App\Enums\PermissionKey;
use App\Models\User;
use App\Services\Clock\Clock;
use App\Services\Clock\SystemClock;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Otp\OtpMac;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\InfobipSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Services\Spreadsheet\CancellationExportWriter;
use App\Services\Spreadsheet\OpenSpoutCancellationExportWriter;
use App\Services\Spreadsheet\OpenSpoutResponseImportReader;
use App\Services\Spreadsheet\ResponseImportReader;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use RuntimeException;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(PhoneNormalizer::class, function (): PhoneNormalizer {
            $allowedCountryCodes = config('phone.allowed_country_codes', ['57']);

            if (! is_array($allowedCountryCodes)) {
                $allowedCountryCodes = ['57'];
            }

            return new PhoneNormalizer(array_values(array_map(
                static fn (mixed $countryCode): string => (string) $countryCode,
                $allowedCountryCodes,
            )));
        });
        $this->app->bind(OtpMac::class, function (): OtpMac {
            $key = (string) config('otp.mac_key', '');

            if ($key === '') {
                throw new RuntimeException('OTP_MAC_KEY must be configured outside the database.');
            }

            return new OtpMac($key);
        });
        $this->app->bind(StatefulGuard::class, function (): StatefulGuard {
            $guard = Auth::guard();

            if (! $guard instanceof StatefulGuard) {
                throw new RuntimeException('The default guard must be stateful.');
            }

            return $guard;
        });

        $this->app->bind(SmsGateway::class, function (): SmsGateway {
            $driver = (string) config('sms.driver');

            if ($driver === 'fake') {
                if ($this->app->environment('production')) {
                    throw new RuntimeException('Fake SMS gateway is forbidden in production.');
                }

                return new FakeSmsGateway($this->app->make(Clock::class));
            }

            if ($driver === 'provider') {
                return new InfobipSmsGateway(
                    endpoint: (string) config('sms.provider.endpoint', ''),
                    authorizationHeader: (string) config('sms.provider.authorization', ''),
                    from: (string) config('sms.provider.from', ''),
                    connectTimeoutSeconds: (int) config('sms.connect_timeout_seconds'),
                    totalTimeoutSeconds: (int) config('sms.total_timeout_seconds'),
                );
            }

            throw new RuntimeException("Unsupported SMS driver {$driver}.");
        });

        $this->app->bind(ResponseImportReader::class, OpenSpoutResponseImportReader::class);
        $this->app->bind(CancellationExportWriter::class, OpenSpoutCancellationExportWriter::class);
    }

    public function boot(): void
    {
        foreach (PermissionKey::cases() as $permissionKey) {
            Gate::define(
                $permissionKey->value,
                static fn (User $user): bool => $user->hasPermission($permissionKey),
            );
        }

        View::composer('layouts.advisor', static function (ViewInstance $view): void {
            $user = Auth::user();
            $permissions = [];

            if ($user instanceof User && $user->isAdvisor()) {
                foreach (
                    DB::table('role_permissions')
                        ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                        ->where('role_permissions.role_id', $user->role_id)
                        ->pluck('permissions.key')
                        ->all() as $key
                ) {
                    if (is_string($key)) {
                        $permissions[$key] = true;
                    }
                }
            }

            $view->with('advisorNavigationPermissions', $permissions);
        });
    }
}
