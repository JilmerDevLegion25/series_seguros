<?php

namespace Tests\Feature\Clients;

use App\DTOs\Clients\ResolveClientData;
use App\Enums\RoleCode;
use App\Models\Role;
use App\Models\User;
use App\Services\Clients\ClientResolver;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class ClientResolverTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_resolver_creates_client_with_initial_password_and_canonical_data(): void
    {
        $client = app(ClientResolver::class)->resolve(new ResolveClientData(
            identity: '1.234.567.890',
            name: ' Cliente Nuevo ',
            email: 'client@example.test',
            phone: '300 123 4567',
        ));

        $this->assertSame(Role::idFor(RoleCode::CLIENT), $client->role_id);
        $this->assertSame('1234567890', $client->username);
        $this->assertSame('1234567890', $client->identity);
        $this->assertSame('+573001234567', $client->phone);
        $this->assertTrue($client->must_change_password);
        $this->assertTrue(Hash::check('1234*', $client->password));
    }

    public function test_resolver_reuses_client_without_resetting_password_and_refreshes_profile(): void
    {
        $resolver = app(ClientResolver::class);
        $client = $resolver->resolve(new ResolveClientData(
            identity: '1234567890',
            name: 'Original',
            email: 'original@example.test',
            phone: '3001234567',
        ));

        $client->forceFill([
            'password' => Hash::make('changed-password'),
            'must_change_password' => false,
        ])->save();

        $resolved = $resolver->resolve(new ResolveClientData(
            identity: '1234567890',
            name: 'Refreshed',
            email: 'fresh@example.test',
            phone: '+573009998888',
        ));

        $this->assertSame($client->id, $resolved->id);
        $this->assertSame('1234567890', $resolved->identity);
        $this->assertSame('Refreshed', $resolved->name);
        $this->assertSame('fresh@example.test', $resolved->email);
        $this->assertSame('+573009998888', $resolved->phone);
        $this->assertFalse($resolved->must_change_password);
        $this->assertTrue(Hash::check('changed-password', $resolved->password));
        $this->assertSame(1, User::query()->where('identity', '1234567890')->count());
    }

    public function test_resolver_race_path_keeps_single_client_for_same_identity(): void
    {
        $resolver = app(ClientResolver::class);

        $first = $resolver->resolve(new ResolveClientData(
            identity: '1234567890',
            name: 'First',
            email: 'first@example.test',
            phone: '3001234567',
        ));

        $second = $resolver->resolve(new ResolveClientData(
            identity: '1 234 567 890',
            name: 'Second',
            email: 'second@example.test',
            phone: '3007654321',
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, User::query()->where('role_id', Role::idFor(RoleCode::CLIENT))->count());
        $this->assertSame('Second', $second->name);
    }
}
