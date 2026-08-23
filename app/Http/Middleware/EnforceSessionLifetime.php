<?php

namespace App\Http\Middleware;

use App\Actions\Auth\LogoutUserAction;
use App\Services\Clock\Clock;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnforceSessionLifetime
{
    public function __construct(
        private Clock $clock,
        private LogoutUserAction $logoutUser,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $now = $this->clock->now()->getTimestamp();
        $startedAt = $request->session()->get('auth_started_at');
        $lastActivityAt = $request->session()->get('auth_last_activity_at');

        if (! is_int($startedAt) || ! is_int($lastActivityAt)) {
            $this->logoutUser->execute($request);

            return $this->expiredResponse();
        }

        if ($now - $lastActivityAt > $this->idleTimeoutSeconds()) {
            $this->logoutUser->execute($request);

            return $this->expiredResponse();
        }

        if ($now - $startedAt > $this->absoluteLifetimeSeconds()) {
            $this->logoutUser->execute($request);

            return $this->expiredResponse();
        }

        $request->session()->put('auth_last_activity_at', $now);

        return $next($request);
    }

    private function expiredResponse(): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'username' => 'La sesión expiró.',
        ]);
    }

    private function idleTimeoutSeconds(): int
    {
        return (int) config('authentication.session.idle_timeout_minutes') * 60;
    }

    private function absoluteLifetimeSeconds(): int
    {
        return (int) config('authentication.session.absolute_lifetime_minutes') * 60;
    }
}
