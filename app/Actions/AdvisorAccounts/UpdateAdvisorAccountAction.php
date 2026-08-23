<?php

namespace App\Actions\AdvisorAccounts;

use App\DTOs\Auth\UpdateAdvisorData;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Normalization\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class UpdateAdvisorAccountAction
{
    public function __construct(
        private PhoneNormalizer $phoneNormalizer,
        private InvalidateUserSessionsAction $invalidateUserSessions,
    ) {}

    public function execute(User $advisor, UpdateAdvisorData $data): User
    {
        if ($advisor->role_id !== Role::idFor(RoleCode::ADVISOR)) {
            throw new InvalidArgumentException('Only Advisor accounts can be updated.');
        }

        $name = trim($data->name);

        if ($name === '') {
            throw new InvalidArgumentException('Advisor name is required.');
        }

        $phone = $data->phone === null ? null : $this->phoneNormalizer->normalize($data->phone);

        return DB::transaction(function () use ($advisor, $data, $name, $phone): User {
            $wasActive = $advisor->status === UserStatus::ACTIVE;

            $advisor->forceFill([
                'name' => $name,
                'email' => $data->email,
                'phone' => $phone,
                'status' => $data->status,
                'identity' => null,
            ])->save();

            if ($wasActive && $data->status === UserStatus::INACTIVE) {
                $this->invalidateUserSessions->execute($advisor);
            }

            return $advisor;
        });
    }
}
