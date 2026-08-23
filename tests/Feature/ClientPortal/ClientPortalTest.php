<?php

namespace Tests\Feature\ClientPortal;

use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\NotificationType;
use App\Enums\OtpPurpose;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\Notification;
use App\Models\OtpChallenge;
use App\Models\Response as CancellationResponse;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class ClientPortalTest extends TestCase
{
    use RefreshPhaseDatabase;

    private int $nextRadicado = 9000;

    public function test_client_dashboard_and_consolidated_portal_show_only_owned_moto_and_credit(): void
    {
        $advisor = $this->makeAdvisor();
        $client = $this->makeClient('1000000001', 'Client Visible');
        $otherClient = $this->makeClient('1000000002', 'Client Ajeno');
        $ownMoto = $this->makeMoto($client, $advisor, ['holder_name' => 'Moto Visible']);
        $ownCredit = $this->makeCredit($client, $advisor, ['holder_name' => 'Credit Visible']);
        $foreignMoto = $this->makeMoto($otherClient, $advisor, ['holder_name' => 'Moto Ajena']);
        $foreignCredit = $this->makeCredit($otherClient, $advisor, ['holder_name' => 'Credit Ajeno']);
        $this->createMotoResponseAndNotification($ownMoto, $advisor, 'Respuesta visible');
        $this->actingAsReady($client);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Panel Client')
            ->assertSee('1')
            ->assertDontSee('Importar respuestas')
            ->assertDontSee('Cuentas Advisor');

        $this->get(route('client.cancellations.index'))
            ->assertOk()
            ->assertSee('Moto Visible')
            ->assertSee('Credit Visible')
            ->assertSee((string) $ownMoto->radicado)
            ->assertSee((string) $ownCredit->radicado)
            ->assertDontSee('Moto Ajena')
            ->assertDontSee('Credit Ajeno')
            ->assertDontSee((string) $foreignMoto->radicado)
            ->assertDontSee((string) $foreignCredit->radicado)
            ->assertDontSee('localStorage');
    }

    public function test_client_detail_shows_snapshot_and_response_without_mutation_controls_or_activity(): void
    {
        $advisor = $this->makeAdvisor();
        $client = $this->makeClient('1000000003', 'Client Detalle');
        $moto = $this->makeMoto($client, $advisor, [
            'holder_name' => 'Snapshot Moto',
            'holder_cedula' => '1000000003',
            'plate' => 'DET123',
        ]);
        $credit = $this->makeCredit($client, $advisor, [
            'holder_name' => 'Snapshot Credit',
            'credit_number' => '0000555000',
        ]);
        $this->createMotoResponseAndNotification($moto, $advisor, 'Cancelacion moto aprobada');
        $this->createCreditResponseAndNotification($credit, $advisor, 'Cancelacion credit aprobada');
        Activity::query()->create([
            'moto_cancellation_id' => $moto->id,
            'actor_user_id' => $advisor->id,
            'type' => ActivityType::RESPONSE_OBTAINED,
            'metadata' => ['internal_note' => 'Actividad interna no visible'],
        ]);
        Audit::query()->create([
            'moto_cancellation_id' => $moto->id,
            'actor_user_id' => $advisor->id,
            'event_type' => AuditEventType::RESPONSE_OBTAINED,
            'request_id' => 'phase09-request-id',
            'metadata' => ['internal_note' => 'Audit interno no visible'],
        ]);
        $this->actingAsReady($client);

        $this->get(route('client.cancellations.moto.show', $moto))
            ->assertOk()
            ->assertSee('Snapshot Moto')
            ->assertSee('1000000003')
            ->assertSee('DET123')
            ->assertSee('2026-08-19')
            ->assertSee('Cancelacion moto aprobada')
            ->assertDontSee('Guardar')
            ->assertDontSee('Reasignar')
            ->assertDontSee('Reintentar SMS')
            ->assertDontSee('Importar')
            ->assertDontSee('Actividad interna no visible')
            ->assertDontSee('Audit interno no visible')
            ->assertDontSee('phase09-request-id')
            ->assertDontSee('owner_user_id')
            ->assertDontSee('created_by_user_id')
            ->assertDontSee('assigned_advisor_user_id')
            ->assertDontSee('version')
            ->assertDontSee('origin')
            ->assertDontSee('localStorage');

        $this->get(route('client.cancellations.credit.show', $credit))
            ->assertOk()
            ->assertSee('Snapshot Credit')
            ->assertSee('0000555000')
            ->assertSee('Cancelacion credit aprobada')
            ->assertDontSee('Guardar')
            ->assertDontSee('Reasignar')
            ->assertDontSee('Reintentar SMS')
            ->assertDontSee('Importar');
    }

    public function test_notifications_are_listed_counted_and_marked_read_idempotently(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-20 09:00:00'));
        $advisor = $this->makeAdvisor();
        $client = $this->makeClient('1000000004', 'Client Notificaciones');
        $moto = $this->makeMoto($client, $advisor);
        $notification = $this->createMotoResponseAndNotification($moto, $advisor, 'Respuesta para notificacion');
        Notification::query()->firstOrCreate(
            [
                'moto_cancellation_id' => $moto->id,
                'type' => NotificationType::RESPONSE_OBTAINED,
            ],
            ['user_id' => $client->id],
        );
        $this->actingAsReady($client);

        $this->assertSame(1, Notification::query()->where('moto_cancellation_id', $moto->id)->count());

        $this->get(route('client.notifications.index'))
            ->assertOk()
            ->assertSee('1 sin leer')
            ->assertSee('No leida')
            ->assertSee((string) $moto->radicado)
            ->assertSee('Respuesta obtenida');

        $this->post(route('client.notifications.read', $notification), [
            '_token' => 'phase09-token',
        ])->assertRedirect(route('client.notifications.index', absolute: false));

        $notification->refresh();
        $firstReadAt = $notification->read_at?->toDateTimeString();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-20 09:05:00'));

        $this->post(route('client.notifications.read', $notification), [
            '_token' => 'phase09-token',
        ])->assertRedirect(route('client.notifications.index', absolute: false));

        $notification->refresh();
        $this->assertSame($firstReadAt, $notification->read_at?->toDateTimeString());
        $this->assertSame(0, Notification::query()->where('user_id', $client->id)->whereNull('read_at')->count());
        $this->assertSame(0, Activity::query()->count());
        $this->assertSame(0, Audit::query()->count());

        $this->get(route('client.notifications.index'))
            ->assertOk()
            ->assertSee('0 sin leer')
            ->assertSee('Leida')
            ->assertSee('Visto');

        CarbonImmutable::setTestNow();
    }

    public function test_notification_and_cancellation_idor_are_concealed_and_advisor_is_denied(): void
    {
        $advisor = $this->makeAdvisor();
        $client = $this->makeClient('1000000005', 'Client Propio');
        $otherClient = $this->makeClient('1000000006', 'Client Ajeno');
        $foreignMoto = $this->makeMoto($otherClient, $advisor, ['holder_name' => 'No Debe Verse']);
        $foreignCredit = $this->makeCredit($otherClient, $advisor, ['holder_name' => 'Credit No Debe Verse']);
        $foreignNotification = $this->createCreditResponseAndNotification($foreignCredit, $advisor, 'Respuesta ajena');
        $this->actingAsReady($client);

        $this->get(route('client.cancellations.moto.show', $foreignMoto))->assertNotFound();
        $this->get(route('client.cancellations.credit.show', $foreignCredit))->assertNotFound();
        $this->post(route('client.notifications.read', $foreignNotification), [
            '_token' => 'phase09-token',
        ])->assertNotFound();
        $this->get(route('client.notifications.index'))
            ->assertOk()
            ->assertDontSee('Respuesta ajena')
            ->assertDontSee('No Debe Verse')
            ->assertDontSee('Credit No Debe Verse');

        $this->actingAsReady($advisor);
        $this->get(route('client.cancellations.index'))->assertForbidden();
        $this->get(route('client.notifications.index'))->assertForbidden();
        $this->post(route('client.notifications.read', $foreignNotification), [
            '_token' => 'phase09-token',
        ])->assertForbidden();
    }

    public function test_public_cedula_lookup_is_not_exposed(): void
    {
        $forbiddenTokens = ['cedula', 'consulta', 'lookup'];

        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $uri = $route->uri();

            if (str_starts_with($name, 'client.') || str_starts_with($name, 'advisor.')) {
                continue;
            }

            foreach ($forbiddenTokens as $token) {
                $this->assertStringNotContainsString($token, strtolower($name));
                $this->assertStringNotContainsString($token, strtolower($uri));
            }
        }

        $this->get('/cedula/1000000001')->assertNotFound();
        $this->get('/consulta?cedula=1000000001')->assertNotFound();
    }

    private function makeClient(string $identity, string $name): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => $identity,
            'identity' => $identity,
            'name' => $name,
            'email' => $identity.'@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function makeAdvisor(): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::ADVISOR),
            'username' => 'advisor-'.$this->nextRadicado,
            'identity' => null,
            'name' => 'Advisor Phase09',
            'email' => 'advisor'.$this->nextRadicado.'@example.test',
            'phone' => '+573009999999',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMoto(User $owner, User $creator, array $overrides = []): MotoCancellation
    {
        /** @var MotoCancellation $moto */
        $moto = MotoCancellation::query()->create(array_merge([
            'otp_challenge_id' => $this->makeOtpChallenge(OtpPurpose::CREATE_MOTO)->id,
            'radicado' => $this->nextRadicado++,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
            'assigned_advisor_user_id' => $creator->id,
            'origin' => CancellationOrigin::ADVISOR,
            'status' => CancellationStatus::EN_GESTION,
            'version' => 1,
            'holder_name' => $owner->name,
            'holder_cedula' => (string) $owner->identity,
            'property_lien_adeinco' => true,
            'plate' => 'ABC123',
            'holder_phone' => (string) $owner->phone,
            'holder_email' => (string) $owner->email,
            'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS,
            'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL,
            'is_credit_holder' => false,
            'credit_owner_name' => 'Credit Owner',
            'credit_owner_cedula' => '9876543210',
            'ownership_declaration_accepted' => true,
            'data_processing_accepted' => true,
        ], $overrides));

        return $moto;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCredit(User $owner, User $creator, array $overrides = []): CreditCancellation
    {
        /** @var CreditCancellation $credit */
        $credit = CreditCancellation::query()->create(array_merge([
            'otp_challenge_id' => $this->makeOtpChallenge(OtpPurpose::CREATE_CREDIT)->id,
            'radicado' => $this->nextRadicado++,
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $creator->id,
            'assigned_advisor_user_id' => $creator->id,
            'origin' => CancellationOrigin::ADVISOR,
            'status' => CancellationStatus::EN_GESTION,
            'version' => 1,
            'holder_name' => $owner->name,
            'holder_cedula' => (string) $owner->identity,
            'credit_number' => '00012345',
            'holder_phone' => (string) $owner->phone,
            'holder_email' => (string) $owner->email,
            'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS,
            'cancel_personal_accidents' => true,
            'cancel_unemployment_insurance' => false,
            'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL,
            'credit_holder_declaration_accepted' => true,
            'data_processing_accepted' => true,
        ], $overrides));

        return $credit;
    }

    private function makeOtpChallenge(OtpPurpose $purpose): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase09-'.bin2hex(random_bytes(8)),
            'purpose' => $purpose,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase09'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }

    private function createMotoResponseAndNotification(MotoCancellation $moto, User $advisor, string $observation): Notification
    {
        $moto->forceFill([
            'status' => CancellationStatus::RESPUESTA_OBTENIDA,
            'version' => $moto->version + 1,
        ])->save();

        CancellationResponse::query()->create([
            'moto_cancellation_id' => $moto->id,
            'cancellation_date' => CarbonImmutable::parse('2026-08-19'),
            'observation' => $observation,
            'created_by_user_id' => $advisor->id,
        ]);

        /** @var Notification $notification */
        $notification = Notification::query()->firstOrCreate(
            [
                'moto_cancellation_id' => $moto->id,
                'type' => NotificationType::RESPONSE_OBTAINED,
            ],
            [
                'user_id' => $moto->owner_user_id,
            ],
        );

        return $notification;
    }

    private function createCreditResponseAndNotification(CreditCancellation $credit, User $advisor, string $observation): Notification
    {
        $credit->forceFill([
            'status' => CancellationStatus::RESPUESTA_OBTENIDA,
            'version' => $credit->version + 1,
        ])->save();

        CancellationResponse::query()->create([
            'credit_cancellation_id' => $credit->id,
            'cancellation_date' => CarbonImmutable::parse('2026-08-18'),
            'observation' => $observation,
            'created_by_user_id' => $advisor->id,
        ]);

        /** @var Notification $notification */
        $notification = Notification::query()->firstOrCreate(
            [
                'credit_cancellation_id' => $credit->id,
                'type' => NotificationType::RESPONSE_OBTAINED,
            ],
            [
                'user_id' => $credit->owner_user_id,
            ],
        );

        return $notification;
    }

    private function actingAsReady(User $user): void
    {
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
                '_token' => 'phase09-token',
            ]);
    }
}
