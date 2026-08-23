<?php

namespace Tests\Feature\Otp;

use App\Actions\Otp\StartPasswordRecoveryAction;
use App\DTOs\Otp\StartPasswordRecoveryData;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\OtpChallenge;
use App\Models\Role;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class PasswordRecoveryTest extends TestCase
{
    use RefreshPhaseDatabase;

    public function test_recovery_start_responds_generically_for_existing_and_missing_accounts(): void
    {
        $this->makeClient();

        $existing = $this->post(route('password.recovery.store'), [
            'username' => '1234567890',
        ]);
        $missing = $this->post(route('password.recovery.store'), [
            'username' => '9999999999',
        ]);

        $existing->assertOk()->assertSee('Si los datos corresponden');
        $missing->assertOk()->assertSee('Si los datos corresponden');
        $this->assertSame(1, count(FakeSmsGateway::sentMessages()));
    }

    public function test_recovery_uses_current_user_phone_as_destination(): void
    {
        $user = $this->makeClient(['phone' => '+573009998888']);

        app(StartPasswordRecoveryAction::class)->execute(new StartPasswordRecoveryData(
            username: $user->username,
            ip: '127.0.0.1',
        ));

        $challenge = OtpChallenge::query()->firstOrFail();

        $this->assertSame($user->id, $challenge->target_user_id);
        $this->assertSame('+573009998888', $challenge->destination_snapshot);
    }

    public function test_valid_recovery_otp_resets_password_invalidates_sessions_and_does_not_auto_login(): void
    {
        $user = $this->makeClient(['password' => Hash::make('old-password')]);
        $this->insertSessionFor($user);

        app(StartPasswordRecoveryAction::class)->execute(new StartPasswordRecoveryData(
            username: $user->username,
            ip: '127.0.0.1',
        ));
        $challenge = OtpChallenge::query()->firstOrFail();
        $otp = $this->latestOtp();

        $this->post(route('password.recovery.update'), [
            'public_reference' => $challenge->public_reference,
            'otp' => $otp,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login', absolute: false));

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue($challenge->fresh()->isConsumed());
        $this->assertGuest();
    }

    public function test_invalid_recovery_otp_does_not_reset_password(): void
    {
        $user = $this->makeClient(['password' => Hash::make('old-password')]);

        app(StartPasswordRecoveryAction::class)->execute(new StartPasswordRecoveryData(
            username: $user->username,
            ip: '127.0.0.1',
        ));
        $challenge = OtpChallenge::query()->firstOrFail();

        $this->from('/password/recovery')->post(route('password.recovery.update'), [
            'public_reference' => $challenge->public_reference,
            'otp' => '000000',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect('/password/recovery')->assertSessionHasErrors('otp');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
        $this->assertSame(1, $challenge->fresh()->failed_attempts);
    }

    public function test_recovery_does_not_create_password_reset_tokens_table(): void
    {
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeClient(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => '1234567890',
            'identity' => '1234567890',
            'name' => 'Recovery Client',
            'email' => 'client@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ], $attributes));
    }

    private function insertSessionFor(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => 'recovery-session-'.$user->id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);
    }

    private function latestOtp(): string
    {
        $messages = FakeSmsGateway::sentMessages();
        $message = end($messages);

        $this->assertIsArray($message);
        $this->assertSame(1, preg_match('/\b(\d{6})\b/', $message['message'], $matches));

        return $matches[1];
    }
}
