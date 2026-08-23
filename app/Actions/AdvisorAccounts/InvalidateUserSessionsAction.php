<?php

namespace App\Actions\AdvisorAccounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class InvalidateUserSessionsAction
{
    public function execute(User $user): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();
    }
}
