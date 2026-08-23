<?php

namespace App\Http\Middleware;

use App\Actions\Auth\LogoutUserAction;
use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureUserIsActive
{
    public function __construct(
        private LogoutUserAction $logoutUser,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User && $user->status === UserStatus::ACTIVE) {
            return $next($request);
        }

        if ($user instanceof User) {
            $this->logoutUser->execute($request);
        }

        abort(403);
    }
}
