<?php

namespace App\Actions\AdvisorAccounts;

use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\TemporaryPasswordGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final readonly class ResetAdvisorPasswordAction
{
    public function __construct(
        private TemporaryPasswordGenerator $temporaryPasswordGenerator,
        private InvalidateUserSessionsAction $invalidateUserSessions,
    ) {}

    /**
     * @return array{user: User, temporary_password: string}
     */
    public function execute(User $advisor): array
    {
        if ($advisor->role_id !== Role::idFor(RoleCode::ADVISOR)) {
            throw new InvalidArgumentException('Only Advisor accounts can receive password resets.');
        }

        return DB::transaction(function () use ($advisor): array {
            $temporaryPassword = $this->temporaryPasswordGenerator->generate();

            $advisor->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
            ])->save();

            $this->invalidateUserSessions->execute($advisor);

            return [
                'user' => $advisor,
                'temporary_password' => $temporaryPassword,
            ];
        });
    }
}
