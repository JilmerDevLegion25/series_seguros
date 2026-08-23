<?php

namespace Database\Seeders;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Actions\Authorization\SyncPermissionCatalogueAction;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\NotificationType;
use App\Enums\OtpPurpose;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\SmsAttemptStatus;
use App\Enums\SmsPurpose;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\Notification;
use App\Models\OtpChallenge;
use App\Models\RadicadoSequence;
use App\Models\Response;
use App\Models\Role;
use App\Models\SmsAttempt;
use App\Models\User;
use App\Services\Otp\OtpMac;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class DevelopmentSeeder extends Seeder
{
    public const CLIENT_PASSWORD = 'DevClient2026*';

    public const ADVISOR_PASSWORD = 'DevAdvisor2026*';

    private const CLIENT_ONE_USERNAME = '1001234567';

    private const CLIENT_TWO_USERNAME = '1007654321';

    private const ADVISOR_ONE_USERNAME = 'ADVISOR_DEMO_1';

    private const ADVISOR_TWO_USERNAME = 'ADVISOR_DEMO_2';

    private const MOTO_RADICADOS = [1001, 1002, 1003, 1004, 1005];

    private const CREDIT_RADICADOS = [2001, 2002, 2003, 2004, 2005];

    public function run(): void
    {
        if ($this->isProduction()) {
            $this->command?->warn('DevelopmentSeeder skipped in production.');

            return;
        }

        app(SyncPermissionCatalogueAction::class)->execute();
        $this->grantAdvisorPermissions();

        DB::transaction(function (): void {
            $users = $this->seedUsers();
            $motos = $this->seedMotoCancellations($users);
            $credits = $this->seedCreditCancellations($users);

            $this->resetOperationalRows($motos, $credits);
            $this->seedResponsesNotificationsActivitiesAuditsAndSms($users, $motos, $credits);
            $this->syncRadicadoSequences();
        });
    }

    private function isProduction(): bool
    {
        return app()->environment('production') || config('app.env') === 'production';
    }

    private function grantAdvisorPermissions(): void
    {
        /** @var Role $advisorRole */
        $advisorRole = Role::query()
            ->where('code', RoleCode::ADVISOR->value)
            ->firstOrFail();
        $grant = app(GrantRolePermissionAction::class);

        foreach (PermissionKey::cases() as $permissionKey) {
            $grant->execute($advisorRole, $permissionKey);
        }
    }

    /**
     * @return array{
     *     client_one: User,
     *     client_two: User,
     *     advisor_one: User,
     *     advisor_two: User
     * }
     */
    private function seedUsers(): array
    {
        $clientRoleId = Role::idFor(RoleCode::CLIENT);
        $advisorRoleId = Role::idFor(RoleCode::ADVISOR);

        return [
            'client_one' => $this->seedUser([
                'role_id' => $clientRoleId,
                'username' => self::CLIENT_ONE_USERNAME,
                'identity' => self::CLIENT_ONE_USERNAME,
                'name' => 'Cliente Pruebas Uno',
                'email' => 'cliente1@example.test',
                'phone' => '+573001234567',
                'password' => Hash::make(self::CLIENT_PASSWORD),
                'status' => UserStatus::ACTIVE,
                'must_change_password' => false,
            ]),
            'client_two' => $this->seedUser([
                'role_id' => $clientRoleId,
                'username' => self::CLIENT_TWO_USERNAME,
                'identity' => self::CLIENT_TWO_USERNAME,
                'name' => 'Cliente Pruebas Dos',
                'email' => 'cliente2@example.test',
                'phone' => '+573002345678',
                'password' => Hash::make(self::CLIENT_PASSWORD),
                'status' => UserStatus::ACTIVE,
                'must_change_password' => false,
            ]),
            'advisor_one' => $this->seedUser([
                'role_id' => $advisorRoleId,
                'username' => self::ADVISOR_ONE_USERNAME,
                'identity' => null,
                'name' => 'Asesor Demo Uno',
                'email' => 'advisor1@example.test',
                'phone' => '+573003456789',
                'password' => Hash::make(self::ADVISOR_PASSWORD),
                'status' => UserStatus::ACTIVE,
                'must_change_password' => false,
            ]),
            'advisor_two' => $this->seedUser([
                'role_id' => $advisorRoleId,
                'username' => self::ADVISOR_TWO_USERNAME,
                'identity' => null,
                'name' => 'Asesor Demo Dos',
                'email' => 'advisor2@example.test',
                'phone' => '+573004567890',
                'password' => Hash::make(self::ADVISOR_PASSWORD),
                'status' => UserStatus::ACTIVE,
                'must_change_password' => false,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedUser(array $attributes): User
    {
        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['username' => $attributes['username']],
            $attributes,
        );

        return $user;
    }

    /**
     * @param  array{client_one: User, client_two: User, advisor_one: User, advisor_two: User}  $users
     * @return array<int, MotoCancellation>
     */
    private function seedMotoCancellations(array $users): array
    {
        $base = CarbonImmutable::now()->subDays(22)->startOfDay()->addHours(9);
        $specs = [
            [
                'radicado' => 1001,
                'owner' => $users['client_one'],
                'creator' => $users['client_one'],
                'assigned' => $users['advisor_one'],
                'origin' => CancellationOrigin::PUBLIC,
                'status' => CancellationStatus::EN_GESTION,
                'version' => 1,
                'at' => $base,
                'holder_name' => 'Cliente Pruebas Uno',
                'holder_cedula' => self::CLIENT_ONE_USERNAME,
                'property_lien_adeinco' => true,
                'plate' => 'MIB101',
                'holder_phone' => '+573001234567',
                'holder_email' => 'cliente1.moto1@example.test',
                'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS,
                'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL,
                'is_credit_holder' => true,
                'credit_owner_name' => null,
                'credit_owner_cedula' => null,
            ],
            [
                'radicado' => 1002,
                'owner' => $users['client_one'],
                'creator' => $users['advisor_one'],
                'assigned' => $users['advisor_one'],
                'origin' => CancellationOrigin::ADVISOR,
                'status' => CancellationStatus::RESPUESTA_OBTENIDA,
                'version' => 2,
                'at' => $base->addDays(3)->addHours(2),
                'holder_name' => 'Cliente Pruebas Uno',
                'holder_cedula' => self::CLIENT_ONE_USERNAME,
                'property_lien_adeinco' => false,
                'plate' => 'MIB102',
                'holder_phone' => '+573001234567',
                'holder_email' => 'cliente1.moto2@example.test',
                'cancellation_reason' => MotoCancellationReason::DESEA_OTRA_ASEGURADORA,
                'cancellation_information_source' => MotoInformationSource::NEGOCIADOR_CARTERA,
                'is_credit_holder' => false,
                'credit_owner_name' => 'Titular Credito Demo Uno',
                'credit_owner_cedula' => '9001234567',
            ],
            [
                'radicado' => 1003,
                'owner' => $users['client_one'],
                'creator' => $users['client_one'],
                'assigned' => $users['advisor_two'],
                'origin' => CancellationOrigin::PUBLIC,
                'status' => CancellationStatus::EN_GESTION,
                'version' => 3,
                'at' => $base->addDays(7)->addHours(1),
                'holder_name' => 'Cliente Pruebas Uno',
                'holder_cedula' => self::CLIENT_ONE_USERNAME,
                'property_lien_adeinco' => true,
                'plate' => 'MIB103',
                'holder_phone' => '+573001234567',
                'holder_email' => 'cliente1.moto3.edited@example.test',
                'cancellation_reason' => MotoCancellationReason::NO_ESTA_INTERESADO,
                'cancellation_information_source' => MotoInformationSource::CORREDOR_DE_SEGURO,
                'is_credit_holder' => false,
                'credit_owner_name' => 'Titular Credito Demo Tres',
                'credit_owner_cedula' => '9007654321',
            ],
            [
                'radicado' => 1004,
                'owner' => $users['client_two'],
                'creator' => $users['advisor_two'],
                'assigned' => $users['advisor_two'],
                'origin' => CancellationOrigin::ADVISOR,
                'status' => CancellationStatus::EN_GESTION,
                'version' => 1,
                'at' => $base->addDays(11)->addHours(3),
                'holder_name' => 'Cliente Pruebas Dos',
                'holder_cedula' => self::CLIENT_TWO_USERNAME,
                'property_lien_adeinco' => false,
                'plate' => 'MIB204',
                'holder_phone' => '+573002345678',
                'holder_email' => 'cliente2.moto1@example.test',
                'cancellation_reason' => MotoCancellationReason::VENTA_DE_LA_MOTO,
                'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL,
                'is_credit_holder' => true,
                'credit_owner_name' => null,
                'credit_owner_cedula' => null,
            ],
            [
                'radicado' => 1005,
                'owner' => $users['client_two'],
                'creator' => $users['advisor_one'],
                'assigned' => $users['advisor_one'],
                'origin' => CancellationOrigin::ADVISOR,
                'status' => CancellationStatus::RESPUESTA_OBTENIDA,
                'version' => 2,
                'at' => $base->addDays(15)->addHours(4),
                'holder_name' => 'Cliente Pruebas Dos',
                'holder_cedula' => self::CLIENT_TWO_USERNAME,
                'property_lien_adeinco' => true,
                'plate' => 'MIB205',
                'holder_phone' => '+573002345678',
                'holder_email' => 'cliente2.moto2@example.test',
                'cancellation_reason' => MotoCancellationReason::PAGO_TOTAL_DE_DEUDA,
                'cancellation_information_source' => MotoInformationSource::NEGOCIADOR_CARTERA,
                'is_credit_holder' => false,
                'credit_owner_name' => 'Titular Credito Demo Dos',
                'credit_owner_cedula' => '9012345678',
            ],
        ];

        $motos = [];

        foreach ($specs as $spec) {
            /** @var User $owner */
            $owner = $spec['owner'];
            /** @var User $creator */
            $creator = $spec['creator'];
            /** @var User $assigned */
            $assigned = $spec['assigned'];
            /** @var CarbonImmutable $createdAt */
            $createdAt = $spec['at'];
            $challenge = $this->terminalChallenge(
                reference: 'dev-moto-'.$spec['radicado'],
                purpose: OtpPurpose::CREATE_MOTO,
                destination: (string) $spec['holder_phone'],
                createdAt: $createdAt,
            );

            /** @var MotoCancellation $moto */
            $moto = MotoCancellation::query()->updateOrCreate(
                ['radicado' => $spec['radicado']],
                [
                    'otp_challenge_id' => $challenge->id,
                    'owner_user_id' => $owner->id,
                    'created_by_user_id' => $creator->id,
                    'assigned_advisor_user_id' => $assigned->id,
                    'origin' => $spec['origin'],
                    'status' => $spec['status'],
                    'version' => $spec['version'],
                    'holder_name' => $spec['holder_name'],
                    'holder_cedula' => $spec['holder_cedula'],
                    'property_lien_adeinco' => $spec['property_lien_adeinco'],
                    'plate' => $spec['plate'],
                    'holder_phone' => $spec['holder_phone'],
                    'holder_email' => $spec['holder_email'],
                    'cancellation_reason' => $spec['cancellation_reason'],
                    'cancellation_information_source' => $spec['cancellation_information_source'],
                    'is_credit_holder' => $spec['is_credit_holder'],
                    'credit_owner_name' => $spec['credit_owner_name'],
                    'credit_owner_cedula' => $spec['credit_owner_cedula'],
                    'ownership_declaration_accepted' => true,
                    'data_processing_accepted' => true,
                ],
            );
            $this->setTimestamps($moto, $createdAt, $createdAt);
            $motos[(int) $spec['radicado']] = $moto->fresh() ?? $moto;
        }

        return $motos;
    }

    /**
     * @param  array{client_one: User, client_two: User, advisor_one: User, advisor_two: User}  $users
     * @return array<int, CreditCancellation>
     */
    private function seedCreditCancellations(array $users): array
    {
        $base = CarbonImmutable::now()->subDays(20)->startOfDay()->addHours(10);
        $specs = [
            [
                'radicado' => 2001,
                'owner' => $users['client_one'],
                'creator' => $users['advisor_two'],
                'assigned' => $users['advisor_two'],
                'origin' => CancellationOrigin::ADVISOR,
                'status' => CancellationStatus::EN_GESTION,
                'version' => 1,
                'at' => $base,
                'holder_name' => 'Cliente Pruebas Uno',
                'holder_cedula' => self::CLIENT_ONE_USERNAME,
                'credit_number' => 'CR-DEV-0001',
                'holder_phone' => '+573001234567',
                'holder_email' => 'cliente1.credit1@example.test',
                'cancellation_reason' => MotoCancellationReason::REDUCIR_GASTOS,
                'cancel_personal_accidents' => true,
                'cancel_unemployment_insurance' => false,
                'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL,
            ],
            [
                'radicado' => 2002,
                'owner' => $users['client_one'],
                'creator' => $users['advisor_one'],
                'assigned' => $users['advisor_one'],
                'origin' => CancellationOrigin::ADVISOR,
                'status' => CancellationStatus::RESPUESTA_OBTENIDA,
                'version' => 2,
                'at' => $base->addDays(4)->addHours(1),
                'holder_name' => 'Cliente Pruebas Uno',
                'holder_cedula' => self::CLIENT_ONE_USERNAME,
                'credit_number' => 'CR-DEV-0002',
                'holder_phone' => '+573001234567',
                'holder_email' => 'cliente1.credit2@example.test',
                'cancellation_reason' => MotoCancellationReason::INCONFORMIDAD_CON_EL_SERVICIO,
                'cancel_personal_accidents' => false,
                'cancel_unemployment_insurance' => true,
                'cancellation_information_source' => MotoInformationSource::NEGOCIADOR_CARTERA,
            ],
            [
                'radicado' => 2003,
                'owner' => $users['client_two'],
                'creator' => $users['client_two'],
                'assigned' => $users['advisor_one'],
                'origin' => CancellationOrigin::PUBLIC,
                'status' => CancellationStatus::EN_GESTION,
                'version' => 1,
                'at' => $base->addDays(8)->addHours(2),
                'holder_name' => 'Cliente Pruebas Dos',
                'holder_cedula' => self::CLIENT_TWO_USERNAME,
                'credit_number' => 'CR-DEV-0003',
                'holder_phone' => '+573002345678',
                'holder_email' => 'cliente2.credit1@example.test',
                'cancellation_reason' => MotoCancellationReason::NO_FUE_INFORMADO_DEL_COBRO,
                'cancel_personal_accidents' => true,
                'cancel_unemployment_insurance' => true,
                'cancellation_information_source' => MotoInformationSource::CORREDOR_DE_SEGURO,
            ],
            [
                'radicado' => 2004,
                'owner' => $users['client_two'],
                'creator' => $users['advisor_two'],
                'assigned' => $users['advisor_two'],
                'origin' => CancellationOrigin::ADVISOR,
                'status' => CancellationStatus::RESPUESTA_OBTENIDA,
                'version' => 2,
                'at' => $base->addDays(12)->addHours(3),
                'holder_name' => 'Cliente Pruebas Dos',
                'holder_cedula' => self::CLIENT_TWO_USERNAME,
                'credit_number' => 'CR-DEV-0004',
                'holder_phone' => '+573002345678',
                'holder_email' => 'cliente2.credit2@example.test',
                'cancellation_reason' => MotoCancellationReason::NO_ESTA_INTERESADO,
                'cancel_personal_accidents' => true,
                'cancel_unemployment_insurance' => false,
                'cancellation_information_source' => MotoInformationSource::ASESOR_COMERCIAL,
            ],
            [
                'radicado' => 2005,
                'owner' => $users['client_two'],
                'creator' => $users['client_two'],
                'assigned' => $users['advisor_one'],
                'origin' => CancellationOrigin::PUBLIC,
                'status' => CancellationStatus::EN_GESTION,
                'version' => 2,
                'at' => $base->addDays(16)->addHours(4),
                'holder_name' => 'Cliente Pruebas Dos',
                'holder_cedula' => self::CLIENT_TWO_USERNAME,
                'credit_number' => 'CR-DEV-0005',
                'holder_phone' => '+573002345678',
                'holder_email' => 'cliente2.credit3@example.test',
                'cancellation_reason' => MotoCancellationReason::PAGO_TOTAL_DE_DEUDA,
                'cancel_personal_accidents' => false,
                'cancel_unemployment_insurance' => true,
                'cancellation_information_source' => MotoInformationSource::NEGOCIADOR_CARTERA,
            ],
        ];

        $credits = [];

        foreach ($specs as $spec) {
            /** @var User $owner */
            $owner = $spec['owner'];
            /** @var User $creator */
            $creator = $spec['creator'];
            /** @var User $assigned */
            $assigned = $spec['assigned'];
            /** @var CarbonImmutable $createdAt */
            $createdAt = $spec['at'];
            $challenge = $this->terminalChallenge(
                reference: 'dev-credit-'.$spec['radicado'],
                purpose: OtpPurpose::CREATE_CREDIT,
                destination: (string) $spec['holder_phone'],
                createdAt: $createdAt,
            );

            /** @var CreditCancellation $credit */
            $credit = CreditCancellation::query()->updateOrCreate(
                ['radicado' => $spec['radicado']],
                [
                    'otp_challenge_id' => $challenge->id,
                    'owner_user_id' => $owner->id,
                    'created_by_user_id' => $creator->id,
                    'assigned_advisor_user_id' => $assigned->id,
                    'origin' => $spec['origin'],
                    'status' => $spec['status'],
                    'version' => $spec['version'],
                    'holder_name' => $spec['holder_name'],
                    'holder_cedula' => $spec['holder_cedula'],
                    'credit_number' => $spec['credit_number'],
                    'holder_phone' => $spec['holder_phone'],
                    'holder_email' => $spec['holder_email'],
                    'cancellation_reason' => $spec['cancellation_reason'],
                    'cancel_personal_accidents' => $spec['cancel_personal_accidents'],
                    'cancel_unemployment_insurance' => $spec['cancel_unemployment_insurance'],
                    'cancellation_information_source' => $spec['cancellation_information_source'],
                    'credit_holder_declaration_accepted' => true,
                    'data_processing_accepted' => true,
                ],
            );
            $this->setTimestamps($credit, $createdAt, $createdAt);
            $credits[(int) $spec['radicado']] = $credit->fresh() ?? $credit;
        }

        return $credits;
    }

    private function terminalChallenge(
        string $reference,
        OtpPurpose $purpose,
        string $destination,
        CarbonImmutable $createdAt,
    ): OtpChallenge {
        $emissionCount = 1;
        $context = $purpose->value.'|'.$reference.'|'.$emissionCount;
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        /** @var OtpChallenge $challenge */
        $challenge = OtpChallenge::query()->updateOrCreate(
            ['public_reference' => $reference],
            [
                'purpose' => $purpose,
                'target_user_id' => null,
                'destination_snapshot' => $destination,
                'encrypted_payload' => null,
                'otp_mac' => app(OtpMac::class)->make($otp, $context),
                'failed_attempts' => 0,
                'emission_count' => $emissionCount,
                'last_emitted_at' => $createdAt,
                'expires_at' => $createdAt->addMinutes(5),
                'consumed_at' => $createdAt->addMinutes(2),
                'invalidated_at' => null,
                'invalidation_reason' => null,
            ],
        );
        $this->setTimestamps($challenge, $createdAt, $createdAt->addMinutes(2));

        return $challenge->fresh() ?? $challenge;
    }

    /**
     * @param  array<int, MotoCancellation>  $motos
     * @param  array<int, CreditCancellation>  $credits
     */
    private function resetOperationalRows(array $motos, array $credits): void
    {
        $motoIds = array_map(static fn (MotoCancellation $moto): int => $moto->id, array_values($motos));
        $creditIds = array_map(static fn (CreditCancellation $credit): int => $credit->id, array_values($credits));
        $responseIds = Response::query()
            ->whereIn('moto_cancellation_id', $motoIds)
            ->orWhereIn('credit_cancellation_id', $creditIds)
            ->pluck('id')
            ->all();

        DB::table('import_row_results')
            ->whereIn('response_id', $responseIds)
            ->orWhereIn('moto_cancellation_id', $motoIds)
            ->orWhereIn('credit_cancellation_id', $creditIds)
            ->delete();

        foreach (['responses', 'notifications', 'sms_attempts', 'audits', 'activities'] as $table) {
            DB::table($table)
                ->whereIn('moto_cancellation_id', $motoIds)
                ->orWhereIn('credit_cancellation_id', $creditIds)
                ->delete();
        }
    }

    /**
     * @param  array{client_one: User, client_two: User, advisor_one: User, advisor_two: User}  $users
     * @param  array<int, MotoCancellation>  $motos
     * @param  array<int, CreditCancellation>  $credits
     */
    private function seedResponsesNotificationsActivitiesAuditsAndSms(array $users, array $motos, array $credits): void
    {
        foreach ($motos as $moto) {
            $this->recordCreated(CancellationType::MOTO, $moto, $moto->creator ?? User::query()->findOrFail($moto->created_by_user_id));
            $this->recordInitialSmsAttempt(CancellationType::MOTO, $moto);
        }

        foreach ($credits as $credit) {
            $this->recordCreated(CancellationType::CREDIT, $credit, $credit->creator ?? User::query()->findOrFail($credit->created_by_user_id));
            $this->recordInitialSmsAttempt(CancellationType::CREDIT, $credit);
        }

        $this->recordMotoUpdate($motos[1003], $users['advisor_one']);
        $this->recordMotoReassignment($motos[1003], $users['advisor_one'], $users['advisor_two']);
        $this->recordCreditReassignment($credits[2005], $users['advisor_two'], $users['advisor_one']);

        $this->recordMotoResponse($motos[1002], $users['advisor_one'], read: false, observation: 'Respuesta demo: la cancelacion Moto fue aprobada por aseguradora.');
        $this->recordCreditResponse($credits[2002], $users['advisor_one'], read: true, observation: 'Respuesta demo: seguros asociados al credito cancelados correctamente.');
        $this->recordCreditResponse($credits[2004], $users['advisor_two'], read: false, observation: 'Respuesta demo: solicitud Credit cerrada con soporte documental.');
        $this->recordMotoResponse($motos[1005], $users['advisor_one'], read: true, observation: 'Respuesta demo: cancelacion Moto efectiva por pago total de deuda.');

        $this->recordManualSmsRetry(CancellationType::MOTO, $motos[1003], $users['advisor_one'], SmsAttemptStatus::FAILED, CarbonImmutable::now()->subHours(6));
        $this->recordManualSmsRetry(CancellationType::MOTO, $motos[1003], $users['advisor_one'], SmsAttemptStatus::SENT, CarbonImmutable::now()->subHours(2));
        $this->recordManualSmsRetry(CancellationType::CREDIT, $credits[2002], $users['advisor_one'], SmsAttemptStatus::UNKNOWN, CarbonImmutable::now()->subHours(3));
    }

    private function recordCreated(CancellationType $type, MotoCancellation|CreditCancellation $cancellation, User $actor): void
    {
        $createdAt = CarbonImmutable::parse((string) $cancellation->created_at);
        $metadata = [
            'event' => AuditEventType::CANCELLATION_CREATED->value,
            'type' => $type->value,
            'radicado' => $cancellation->radicado,
            'origin' => $cancellation->origin->value,
        ];

        $this->createActivity($type, $cancellation, $actor, ActivityType::CREATED, $metadata, $createdAt);
        $this->createAudit($type, $cancellation, $actor, AuditEventType::CANCELLATION_CREATED, $metadata, $createdAt, 'seed-create-'.$type->value.'-'.$cancellation->radicado);
    }

    private function recordMotoUpdate(MotoCancellation $moto, User $actor): void
    {
        $createdAt = CarbonImmutable::parse((string) $moto->created_at)->addDay();
        $metadata = [
            'event' => AuditEventType::CANCELLATION_UPDATED->value,
            'type' => CancellationType::MOTO->value,
            'radicado' => $moto->radicado,
            'reason' => 'Ajuste de correo demo para pruebas UI.',
            'version' => 2,
            'changed_fields' => ['holder_email'],
            'changes' => [
                [
                    'field' => 'holder_email',
                    'before' => 'cliente1.moto3@example.test',
                    'after' => $moto->holder_email,
                ],
            ],
        ];

        $this->createActivity(CancellationType::MOTO, $moto, $actor, ActivityType::UPDATED, $metadata, $createdAt);
        $this->createAudit(CancellationType::MOTO, $moto, $actor, AuditEventType::CANCELLATION_UPDATED, $metadata, $createdAt, 'seed-update-moto-'.$moto->radicado);
    }

    private function recordMotoReassignment(MotoCancellation $moto, User $fromAdvisor, User $toAdvisor): void
    {
        $createdAt = CarbonImmutable::parse((string) $moto->created_at)->addDays(2);
        $metadata = $this->reassignmentMetadata(CancellationType::MOTO, $moto->radicado, 3, $fromAdvisor, $toAdvisor);

        $this->createActivity(CancellationType::MOTO, $moto, $fromAdvisor, ActivityType::OWNER_REASSIGNED, $metadata, $createdAt);
        $this->createAudit(CancellationType::MOTO, $moto, $fromAdvisor, AuditEventType::OWNER_REASSIGNED, $metadata, $createdAt, 'seed-reassign-moto-'.$moto->radicado);
    }

    private function recordCreditReassignment(CreditCancellation $credit, User $fromAdvisor, User $toAdvisor): void
    {
        $createdAt = CarbonImmutable::parse((string) $credit->created_at)->addDay();
        $metadata = $this->reassignmentMetadata(CancellationType::CREDIT, $credit->radicado, 2, $fromAdvisor, $toAdvisor);

        $this->createActivity(CancellationType::CREDIT, $credit, $fromAdvisor, ActivityType::OWNER_REASSIGNED, $metadata, $createdAt);
        $this->createAudit(CancellationType::CREDIT, $credit, $fromAdvisor, AuditEventType::OWNER_REASSIGNED, $metadata, $createdAt, 'seed-reassign-credit-'.$credit->radicado);
    }

    /**
     * @return array<string, mixed>
     */
    private function reassignmentMetadata(CancellationType $type, int $radicado, int $version, User $fromAdvisor, User $toAdvisor): array
    {
        return [
            'event' => AuditEventType::OWNER_REASSIGNED->value,
            'type' => $type->value,
            'radicado' => $radicado,
            'reason' => 'Reasignacion demo para probar filtros e historial.',
            'version' => $version,
            'changed_fields' => ['assigned_advisor_user_id'],
            'changes' => [
                [
                    'field' => 'assigned_advisor_user_id',
                    'before' => $fromAdvisor->id,
                    'after' => $toAdvisor->id,
                ],
            ],
        ];
    }

    private function recordMotoResponse(MotoCancellation $moto, User $advisor, bool $read, string $observation): void
    {
        $responseAt = CarbonImmutable::parse((string) $moto->created_at)->addDays(2)->setTime(15, 30);
        /** @var Response $response */
        $response = Response::query()->create([
            'moto_cancellation_id' => $moto->id,
            'credit_cancellation_id' => null,
            'cancellation_date' => $responseAt->toDateString(),
            'observation' => $observation,
            'created_by_user_id' => $advisor->id,
        ]);
        $this->setCreatedAt($response, $responseAt);
        $this->recordResponseObtained(CancellationType::MOTO, $moto, $advisor, $response, $responseAt);
        $this->recordNotification(CancellationType::MOTO, $moto, read: $read, createdAt: $responseAt);
    }

    private function recordCreditResponse(CreditCancellation $credit, User $advisor, bool $read, string $observation): void
    {
        $responseAt = CarbonImmutable::parse((string) $credit->created_at)->addDays(2)->setTime(16, 0);
        /** @var Response $response */
        $response = Response::query()->create([
            'moto_cancellation_id' => null,
            'credit_cancellation_id' => $credit->id,
            'cancellation_date' => $responseAt->toDateString(),
            'observation' => $observation,
            'created_by_user_id' => $advisor->id,
        ]);
        $this->setCreatedAt($response, $responseAt);
        $this->recordResponseObtained(CancellationType::CREDIT, $credit, $advisor, $response, $responseAt);
        $this->recordNotification(CancellationType::CREDIT, $credit, read: $read, createdAt: $responseAt);
    }

    private function recordResponseObtained(
        CancellationType $type,
        MotoCancellation|CreditCancellation $cancellation,
        User $advisor,
        Response $response,
        CarbonImmutable $createdAt,
    ): void {
        $metadata = [
            'event' => AuditEventType::RESPONSE_OBTAINED->value,
            'type' => $type->value,
            'radicado' => $cancellation->radicado,
            'version' => $cancellation->version,
            'response_id' => $response->id,
        ];

        $this->createActivity($type, $cancellation, $advisor, ActivityType::RESPONSE_OBTAINED, $metadata, $createdAt);
        $this->createAudit($type, $cancellation, $advisor, AuditEventType::RESPONSE_OBTAINED, $metadata, $createdAt, 'seed-response-'.$type->value.'-'.$cancellation->radicado);
    }

    private function recordNotification(CancellationType $type, MotoCancellation|CreditCancellation $cancellation, bool $read, CarbonImmutable $createdAt): void
    {
        /** @var Notification $notification */
        $notification = Notification::query()->create([
            'user_id' => $cancellation->owner_user_id,
            'type' => NotificationType::RESPONSE_OBTAINED,
            'moto_cancellation_id' => $type === CancellationType::MOTO ? $cancellation->id : null,
            'credit_cancellation_id' => $type === CancellationType::CREDIT ? $cancellation->id : null,
            'read_at' => $read ? $createdAt->addDay() : null,
        ]);
        $this->setCreatedAt($notification, $createdAt);
    }

    private function recordInitialSmsAttempt(CancellationType $type, MotoCancellation|CreditCancellation $cancellation): void
    {
        $createdAt = CarbonImmutable::parse((string) $cancellation->created_at)->addMinutes(5);
        $status = match ($cancellation->radicado) {
            1003, 2002 => SmsAttemptStatus::FAILED,
            1004, 2004 => SmsAttemptStatus::UNKNOWN,
            default => SmsAttemptStatus::SENT,
        };

        $this->createSmsAttempt($type, $cancellation, $status, manualRetry: false, createdAt: $createdAt);
    }

    private function recordManualSmsRetry(
        CancellationType $type,
        MotoCancellation|CreditCancellation $cancellation,
        User $advisor,
        SmsAttemptStatus $status,
        CarbonImmutable $createdAt,
    ): void {
        $attempt = $this->createSmsAttempt($type, $cancellation, $status, manualRetry: true, createdAt: $createdAt);
        $metadata = [
            'type' => $type->value,
            'radicado' => $cancellation->radicado,
            'sms_attempt_id' => $attempt->id,
            'purpose' => SmsPurpose::RADICADO->value,
            'destination_snapshot' => $this->maskDestination($attempt->destination),
        ];

        $this->createAudit(
            $type,
            $cancellation,
            $advisor,
            AuditEventType::RADICADO_SMS_RETRY_REQUESTED,
            $metadata,
            $createdAt,
            'seed-sms-retry-'.$type->value.'-'.$cancellation->radicado.'-'.$attempt->id,
        );
    }

    private function createSmsAttempt(
        CancellationType $type,
        MotoCancellation|CreditCancellation $cancellation,
        SmsAttemptStatus $status,
        bool $manualRetry,
        CarbonImmutable $createdAt,
    ): SmsAttempt {
        /** @var SmsAttempt $attempt */
        $attempt = SmsAttempt::query()->create([
            'moto_cancellation_id' => $type === CancellationType::MOTO ? $cancellation->id : null,
            'credit_cancellation_id' => $type === CancellationType::CREDIT ? $cancellation->id : null,
            'purpose' => SmsPurpose::RADICADO,
            'status' => $status,
            'is_manual_retry' => $manualRetry,
            'destination' => $cancellation->holder_phone,
            'provider_reference' => $status === SmsAttemptStatus::SENT
                ? 'fake-dev-'.$type->value.'-'.$cancellation->radicado.'-'.($manualRetry ? 'retry' : 'initial')
                : null,
            'safe_error_code' => $status === SmsAttemptStatus::FAILED ? 'DEV_PROVIDER_FAILED' : ($status === SmsAttemptStatus::UNKNOWN ? 'DEV_PROVIDER_UNKNOWN' : null),
            'safe_error_message' => $status === SmsAttemptStatus::SENT ? null : 'Provider error.',
            'completed_at' => $createdAt,
        ]);
        $this->setTimestamps($attempt, $createdAt, $createdAt);

        return $attempt->fresh() ?? $attempt;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createActivity(
        CancellationType $type,
        MotoCancellation|CreditCancellation $cancellation,
        User $actor,
        ActivityType $activityType,
        array $metadata,
        CarbonImmutable $createdAt,
    ): Activity {
        /** @var Activity $activity */
        $activity = Activity::query()->create([
            'moto_cancellation_id' => $type === CancellationType::MOTO ? $cancellation->id : null,
            'credit_cancellation_id' => $type === CancellationType::CREDIT ? $cancellation->id : null,
            'actor_user_id' => $actor->id,
            'type' => $activityType,
            'metadata' => $metadata,
        ]);
        $this->setCreatedAt($activity, $createdAt);

        return $activity;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createAudit(
        CancellationType $type,
        MotoCancellation|CreditCancellation $cancellation,
        User $actor,
        AuditEventType $eventType,
        array $metadata,
        CarbonImmutable $createdAt,
        string $requestId,
    ): Audit {
        /** @var Audit $audit */
        $audit = Audit::query()->create([
            'moto_cancellation_id' => $type === CancellationType::MOTO ? $cancellation->id : null,
            'credit_cancellation_id' => $type === CancellationType::CREDIT ? $cancellation->id : null,
            'actor_user_id' => $actor->id,
            'event_type' => $eventType,
            'request_id' => $requestId,
            'metadata' => $metadata,
        ]);
        $this->setCreatedAt($audit, $createdAt);

        return $audit;
    }

    private function syncRadicadoSequences(): void
    {
        foreach ([CancellationType::MOTO, CancellationType::CREDIT] as $type) {
            $maxSeeded = $type === CancellationType::MOTO
                ? max(self::MOTO_RADICADOS)
                : max(self::CREDIT_RADICADOS);
            $table = $type === CancellationType::MOTO
                ? 'moto_cancellations'
                : 'credit_cancellations';
            $maxExisting = DB::table($table)->max('radicado');
            $nextValue = max($maxSeeded, is_numeric($maxExisting) ? (int) $maxExisting : 0) + 1;

            RadicadoSequence::query()->updateOrCreate(
                ['type' => $type],
                ['next_value' => $nextValue],
            );
        }
    }

    private function maskDestination(string $destination): string
    {
        if (strlen($destination) <= 2) {
            return str_repeat('*', strlen($destination));
        }

        return str_repeat('*', strlen($destination) - 2).substr($destination, -2);
    }

    private function setCreatedAt(Model $model, CarbonImmutable $createdAt): void
    {
        DB::table($model->getTable())
            ->where('id', $model->getKey())
            ->update(['created_at' => $createdAt->toDateTimeString()]);
    }

    private function setTimestamps(Model $model, CarbonImmutable $createdAt, CarbonImmutable $updatedAt): void
    {
        DB::table($model->getTable())
            ->where('id', $model->getKey())
            ->update([
                'created_at' => $createdAt->toDateTimeString(),
                'updated_at' => $updatedAt->toDateTimeString(),
            ]);
    }
}
