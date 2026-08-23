<?php

namespace App\Services\Clients;

use App\DTOs\Clients\ResolveClientData;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final readonly class ClientResolver
{
    public function __construct(
        private IdentityNormalizer $identityNormalizer,
        private PhoneNormalizer $phoneNormalizer,
    ) {}

    public function resolve(ResolveClientData $data): User
    {
        $identity = $this->identityNormalizer->normalize($data->identity);
        $phone = $this->phoneNormalizer->normalize($data->phone);
        $name = trim($data->name);
        $email = trim($data->email);

        if ($name === '' || $email === '') {
            throw new RuntimeException('Client name and email are required.');
        }

        return DB::transaction(function () use ($identity, $name, $email, $phone): User {
            $now = now();

            DB::table('users')->insertOrIgnore([
                'role_id' => Role::idFor(RoleCode::CLIENT),
                'username' => $identity,
                'identity' => $identity,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make((string) config('authentication.client_initial_password')),
                'status' => UserStatus::ACTIVE->value,
                'must_change_password' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $client = $this->findClientForUpdate($identity);

            if (! $client instanceof User) {
                throw new RuntimeException('Unable to resolve client.');
            }

            $client->forceFill([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
            ])->save();

            return $client;
        });
    }

    private function findClientForUpdate(string $identity): ?User
    {
        /** @var User|null $client */
        $client = User::query()
            ->where('role_id', Role::idFor(RoleCode::CLIENT))
            ->where('identity', $identity)
            ->lockForUpdate()
            ->first();

        return $client;
    }
}
