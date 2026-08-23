<?php

namespace App\Actions\Auth;

use App\DTOs\Auth\LoginData;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Clock\Clock;
use App\Services\Normalization\UsernameNormalizer;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final readonly class AuthenticateUserAction
{
    private const GENERIC_ERROR = 'Credenciales inválidas.';

    public function __construct(
        private StatefulGuard $guard,
        private Clock $clock,
        private UsernameNormalizer $usernameNormalizer,
    ) {}

    public function execute(LoginData $data, Request $request): User
    {
        $username = $this->usernameNormalizer->normalizeLogin($data->username);
        $accountKey = $this->accountRateLimitKey($username);
        $ipKey = $this->ipRateLimitKey((string) $request->ip());

        $this->ensureIsNotRateLimited($accountKey, $ipKey, $request);

        $user = User::query()->where('username', $username)->first();

        if (! $user instanceof User || ! Hash::check($data->password, $user->password)) {
            $this->hitFailureLimits($accountKey, $ipKey);
            $this->throwGenericFailure();
        }

        if ($user->status !== UserStatus::ACTIVE) {
            $this->hitFailureLimits($accountKey, $ipKey);
            $this->throwGenericFailure();
        }

        RateLimiter::clear($accountKey);
        RateLimiter::clear($ipKey);

        $this->guard->login($user, remember: false);
        $request->session()->regenerate();

        $now = $this->clock->now()->getTimestamp();
        $request->session()->put('auth_started_at', $now);
        $request->session()->put('auth_last_activity_at', $now);

        return $user;
    }

    private function ensureIsNotRateLimited(string $accountKey, string $ipKey, Request $request): void
    {
        $accountMax = (int) config('authentication.rate_limits.account_failures');
        $ipMax = (int) config('authentication.rate_limits.ip_failures');

        if (! RateLimiter::tooManyAttempts($accountKey, $accountMax)
            && ! RateLimiter::tooManyAttempts($ipKey, $ipMax)) {
            return;
        }

        event(new Lockout($request));

        throw new TooManyRequestsHttpException(null, self::GENERIC_ERROR);
    }

    private function hitFailureLimits(string $accountKey, string $ipKey): void
    {
        RateLimiter::hit($accountKey, (int) config('authentication.rate_limits.account_decay_seconds'));
        RateLimiter::hit($ipKey, (int) config('authentication.rate_limits.ip_decay_seconds'));
    }

    private function throwGenericFailure(): never
    {
        throw ValidationException::withMessages([
            'username' => self::GENERIC_ERROR,
        ]);
    }

    private function accountRateLimitKey(string $username): string
    {
        return 'login:account:'.sha1($username);
    }

    private function ipRateLimitKey(string $ip): string
    {
        return 'login:ip:'.sha1($ip);
    }
}
