<?php

namespace App\Actions\Auth;

use App\DTOs\Auth\CreateAdvisorData;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\TemporaryPasswordGenerator;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Normalization\UsernameNormalizer;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final readonly class CreateAdvisorAction
{
    public function __construct(
        private UsernameNormalizer $usernameNormalizer,
        private PhoneNormalizer $phoneNormalizer,
        private TemporaryPasswordGenerator $temporaryPasswordGenerator,
    ) {}

    /**
     * @return array{user: User, temporary_password: string}
     */
    public function execute(CreateAdvisorData $data): array
    {
        $username = $this->usernameNormalizer->normalizeAdvisor($data->username);
        $name = trim($data->name);

        if ($name === '') {
            throw new InvalidArgumentException('Advisor name is required.');
        }

        $phone = $data->phone === null ? null : $this->phoneNormalizer->normalize($data->phone);
        $temporaryPassword = $this->temporaryPasswordGenerator->generate();

        $user = User::query()->create([
            'role_id' => Role::idFor(RoleCode::ADVISOR),
            'username' => $username,
            'identity' => null,
            'name' => $name,
            'email' => $data->email,
            'phone' => $phone,
            'password' => Hash::make($temporaryPassword),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => true,
        ]);

        return ['user' => $user, 'temporary_password' => $temporaryPassword];
    }
}
