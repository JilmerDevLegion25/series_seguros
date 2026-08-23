<?php

namespace Tests\Feature\Exports;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\OtpPurpose;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\MotoCancellation;
use App\Models\OtpChallenge;
use App\Models\Response as CancellationResponse;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class CancellationExportTest extends TestCase
{
    use RefreshPhaseDatabase {
        setUp as refreshPhaseDatabaseSetUp;
    }

    private int $nextRadicado = 11000;

    protected function setUp(): void
    {
        $this->refreshPhaseDatabaseSetUp();
        Storage::disk('exports')->deleteDirectory('cancellations');
    }

    public function test_export_requires_advisor_export_permission_and_is_available_without_view_permission(): void
    {
        $advisor = $this->makeAdvisor('advisor-export-perm', 'Advisor Export Permission');
        $client = $this->makeClient('1000001001', 'Client Export Permission');
        $this->makeMoto($client, $advisor, ['holder_name' => 'Permission Moto']);
        $this->actingAsReady($advisor);

        $this->post(route('advisor.cancellations.export'), ['_token' => 'phase11-token'])
            ->assertForbidden();

        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_EXPORT);

        $response = $this->post(route('advisor.cancellations.export'), [
            '_token' => 'phase11-token',
            'type' => 'MOTO',
        ]);
        $response->assertOk();
        $this->assertDownloadResponse($response->baseResponse);

        $audit = Audit::query()->where('event_type', AuditEventType::EXPORT_GENERATED)->firstOrFail();
        $this->assertNull($audit->moto_cancellation_id);
        $this->assertNull($audit->credit_cancellation_id);
        $this->assertSame($advisor->id, $audit->actor_user_id);
        $this->assertSame(1, $audit->metadata['row_count']);
        $this->assertSame(0, Activity::query()->count());

        $this->actingAsReady($client);
        $this->post(route('advisor.cancellations.export'), ['_token' => 'phase11-token'])
            ->assertForbidden();
    }

    public function test_export_page_requires_export_permission_and_collects_creation_date_range(): void
    {
        $advisor = $this->makeAdvisor('advisor-export-page', 'Advisor Export Page');
        $this->actingAsReady($advisor);

        $this->get(route('advisor.cancellations.export.create'))
            ->assertForbidden();

        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_EXPORT);

        $this->get(route('advisor.cancellations.export.create', [
            'created_from' => '2026-08-18',
            'created_to' => '2026-08-19',
            'type' => 'MOTO',
        ]))
            ->assertOk()
            ->assertSee('Exportar cancelaciones')
            ->assertSee('data-export-current-label', false)
            ->assertSee('Moto')
            ->assertSee('name="type"', false)
            ->assertSee('type="hidden" value="MOTO"', false)
            ->assertSee('data-export-type="CREDIT"', false)
            ->assertSee('Fecha inicio')
            ->assertSee('Fecha fin')
            ->assertSee('name="created_from"', false)
            ->assertSee('value="2026-08-18"', false)
            ->assertSee('name="created_to"', false)
            ->assertSee('value="2026-08-19"', false)
            ->assertSee('Descargar XLSX');
    }

    public function test_export_workbook_contains_only_selected_moto_sheet_with_allowlisted_columns(): void
    {
        $advisor = $this->makeAdvisor('advisor-workbook', 'Advisor Workbook');
        $owner = $this->makeClient('1000001002', 'Angela Workbook');
        $moto = $this->makeMoto($owner, $advisor, [
            'radicado' => 90001,
            'holder_name' => 'Angela Moto',
            'holder_cedula' => '1000001002',
            'holder_email' => 'angela.moto@example.test',
            'holder_phone' => '+573001111111',
            'plate' => 'MOT123',
            'is_credit_holder' => false,
            'credit_owner_name' => 'Carlos Credit Owner',
            'credit_owner_cedula' => '1000009999',
            'created_at' => '2026-08-18 09:00:00',
            'updated_at' => '2026-08-18 09:00:00',
        ]);
        $credit = $this->makeCredit($owner, $advisor, [
            'radicado' => 90002,
            'holder_name' => 'Angela Credit',
            'holder_cedula' => '1000001002',
            'holder_email' => 'angela.credit@example.test',
            'holder_phone' => '+573002222222',
            'credit_number' => '000CR777',
            'cancel_personal_accidents' => true,
            'cancel_unemployment_insurance' => false,
            'created_at' => '2026-08-19 10:00:00',
            'updated_at' => '2026-08-19 10:00:00',
        ]);
        $this->createMotoResponse($moto, $advisor, '2026-08-19', 'Moto respondida');
        $this->createCreditResponse($credit, $advisor, '2026-08-20', 'Credit respondido');
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_EXPORT);
        $this->actingAsReady($advisor);

        $response = $this->post(route('advisor.cancellations.export'), [
            '_token' => 'phase11-token',
            'type' => 'MOTO',
        ]);
        $path = $this->downloadPath($response->baseResponse);
        $workbook = $this->workbookRows($path);

        $this->assertSame(['Moto'], array_keys($workbook));
        $this->assertContainsNoInternalColumns($workbook['Moto'][0]);
        $this->assertSame('MOT123', $workbook['Moto'][1][1]);
        $this->assertSame('Angela Moto', $workbook['Moto'][1][2]);
        $this->assertSame('1000001002', $workbook['Moto'][1][3]);
        $this->assertSame('3001111111', $workbook['Moto'][1][4]);
        $this->assertSame('angela.moto@example.test', $workbook['Moto'][1][5]);
        $this->assertSame('NO', $workbook['Moto'][1][8]);
        $this->assertSame('Carlos Credit Owner', $workbook['Moto'][1][9]);
        $this->assertSame('1000009999', $workbook['Moto'][1][10]);
        $this->assertSame('90001', $workbook['Moto'][1][11]);
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::EXPORT_GENERATED)->count());
        $this->assertSame(0, Activity::query()->where('type', ActivityType::RESPONSE_OBTAINED)->whereNull('moto_cancellation_id')->whereNull('credit_cancellation_id')->count());
    }

    public function test_export_reuses_phase10_filters_and_exports_all_filtered_rows_not_the_current_page(): void
    {
        $advisorOne = $this->makeAdvisor('advisor-filter-one', 'Advisor Filter One');
        $advisorTwo = $this->makeAdvisor('advisor-filter-two', 'Advisor Filter Two');
        $ownerOne = $this->makeClient('1000001003', 'Owner Filter One');
        $ownerTwo = $this->makeClient('1000001004', 'Owner Filter Two');
        $this->makeMoto($ownerOne, $advisorOne, [
            'holder_name' => 'Filtered Moto One',
            'holder_email' => 'filter@example.test',
            'plate' => 'FLT001',
            'assigned_advisor_user_id' => $advisorTwo->id,
            'created_at' => '2026-08-18 10:00:00',
            'updated_at' => '2026-08-18 10:00:00',
        ]);
        $this->makeMoto($ownerOne, $advisorOne, [
            'holder_name' => 'Filtered Moto Two',
            'holder_email' => 'filter@example.test',
            'plate' => 'FLT002',
            'assigned_advisor_user_id' => $advisorTwo->id,
            'created_at' => '2026-08-19 10:00:00',
            'updated_at' => '2026-08-19 10:00:00',
        ]);
        $this->makeCredit($ownerTwo, $advisorOne, [
            'holder_name' => 'Filtered Credit Noise',
            'holder_email' => 'filter@example.test',
            'credit_number' => 'CREDITNOISE',
            'assigned_advisor_user_id' => $advisorTwo->id,
        ]);
        $this->makeMoto($ownerTwo, $advisorOne, [
            'holder_name' => 'Unassigned Noise',
            'holder_email' => 'noise@example.test',
            'plate' => 'NOI001',
            'assigned_advisor_user_id' => $advisorOne->id,
        ]);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_EXPORT);
        $this->actingAsReady($advisorOne);

        $response = $this->post(route('advisor.cancellations.export'), [
            '_token' => 'phase11-token',
            'type' => 'MOTO',
            'holder_email' => 'FILTER@EXAMPLE.TEST',
            'assigned_advisor_user_id' => $advisorTwo->id,
            'per_page' => 1,
            'sort' => 'created_at_asc',
        ]);
        $workbook = $this->workbookRows($this->downloadPath($response->baseResponse));

        $this->assertCount(3, $workbook['Moto']);
        $this->assertSame('Filtered Moto One', $workbook['Moto'][1][2]);
        $this->assertSame('Filtered Moto Two', $workbook['Moto'][2][2]);
        $this->assertSame(['Moto'], array_keys($workbook));
    }

    public function test_export_date_range_filters_by_cancellation_creation_date(): void
    {
        $advisor = $this->makeAdvisor('advisor-date-range', 'Advisor Date Range');
        $owner = $this->makeClient('1000001010', 'Owner Date Range');
        $this->makeMoto($owner, $advisor, [
            'holder_name' => 'Old Moto',
            'created_at' => '2026-08-17 23:59:59',
            'updated_at' => '2026-08-17 23:59:59',
        ]);
        $this->makeMoto($owner, $advisor, [
            'holder_name' => 'Range Moto',
            'created_at' => '2026-08-18 12:00:00',
            'updated_at' => '2026-08-18 12:00:00',
        ]);
        $this->makeCredit($owner, $advisor, [
            'holder_name' => 'Range Credit',
            'created_at' => '2026-08-19 23:59:59',
            'updated_at' => '2026-08-19 23:59:59',
        ]);
        $this->makeCredit($owner, $advisor, [
            'holder_name' => 'Late Credit',
            'created_at' => '2026-08-20 00:00:00',
            'updated_at' => '2026-08-20 00:00:00',
        ]);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_EXPORT);
        $this->actingAsReady($advisor);

        $response = $this->post(route('advisor.cancellations.export'), [
            '_token' => 'phase11-token',
            'type' => 'CREDIT',
            'created_from' => '2026-08-18',
            'created_to' => '2026-08-19',
        ]);
        $workbook = $this->workbookRows($this->downloadPath($response->baseResponse));

        $this->assertSame(['Credit'], array_keys($workbook));
        $this->assertCount(2, $workbook['Credit']);
        $this->assertSame('Range Credit', $workbook['Credit'][1][2]);
    }

    public function test_export_uses_historical_holder_snapshot_not_current_user_profile(): void
    {
        $advisor = $this->makeAdvisor('advisor-snapshot', 'Advisor Snapshot');
        $owner = $this->makeClient('1000001005', 'Original Holder');
        $moto = $this->makeMoto($owner, $advisor, [
            'holder_name' => 'Historical Holder',
            'holder_email' => 'historical@example.test',
            'holder_phone' => '+573003333333',
            'holder_cedula' => '1000001005',
        ]);
        $owner->forceFill([
            'name' => 'Changed Holder',
            'email' => 'changed@example.test',
            'phone' => '+573009999999',
        ])->save();
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_EXPORT);
        $this->actingAsReady($advisor);

        $workbook = $this->workbookRows($this->downloadPath(
            $this->post(route('advisor.cancellations.export'), [
                '_token' => 'phase11-token',
                'type' => 'MOTO',
                'radicado' => $moto->radicado,
            ])->baseResponse,
        ));

        $this->assertSame('Historical Holder', $workbook['Moto'][1][2]);
        $this->assertSame('historical@example.test', $workbook['Moto'][1][5]);
        $this->assertSame('3003333333', $workbook['Moto'][1][4]);
        $this->assertNotContains('Changed Holder', $workbook['Moto'][1]);
        $this->assertNotContains('changed@example.test', $workbook['Moto'][1]);
        $this->assertSame(1, $moto->version);
    }

    public function test_export_mitigates_formula_injection_and_preserves_utf8_text(): void
    {
        $advisor = $this->makeAdvisor('advisor-formula', 'Advisor Formula');
        $owner = $this->makeClient('1000001006', 'Owner Formula');
        $credit = $this->makeCredit($owner, $advisor, [
            'holder_name' => '=HYPERLINK("http://example.test","Angela")',
            'holder_email' => 'utf8@example.test',
            'credit_number' => '@000777',
            'cancellation_reason' => MotoCancellationReason::INCONFORMIDAD_CON_EL_SERVICIO,
        ]);
        $this->createCreditResponse($credit, $advisor, '2026-08-20', '+SUM(1,1) con acento Á');
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_EXPORT);
        $this->actingAsReady($advisor);

        $workbook = $this->workbookRows($this->downloadPath(
            $this->post(route('advisor.cancellations.export'), [
                '_token' => 'phase11-token',
                'type' => 'CREDIT',
            ])->baseResponse,
        ));

        $this->assertSame(['Credit'], array_keys($workbook));
        $this->assertSame('\'=HYPERLINK("http://example.test","Angela")', $workbook['Credit'][1][2]);
        $this->assertSame("'@000777", $workbook['Credit'][1][3]);
        $this->assertNotContains('+SUM(1,1) con acento Á', $workbook['Credit'][1]);
    }

    public function test_export_rejects_more_than_configured_max_without_file_or_audit(): void
    {
        config(['exports.max_rows' => 1]);
        $advisor = $this->makeAdvisor('advisor-limit', 'Advisor Limit');
        $owner = $this->makeClient('1000001007', 'Owner Limit');
        $this->makeMoto($owner, $advisor, ['holder_name' => 'Limit One']);
        $this->makeMoto($owner, $advisor, ['holder_name' => 'Limit Two']);
        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_EXPORT);
        $this->actingAsReady($advisor);

        $this->from(route('advisor.cancellations.index'))
            ->post(route('advisor.cancellations.export'), [
                '_token' => 'phase11-token',
                'type' => 'MOTO',
            ])
            ->assertRedirect(route('advisor.cancellations.index', absolute: false))
            ->assertSessionHasErrors(['export']);

        $this->assertSame(0, Audit::query()->where('event_type', AuditEventType::EXPORT_GENERATED)->count());
        $this->assertSame([], Storage::disk('exports')->files('cancellations'));
    }

    public function test_export_private_download_headers_filename_and_streaming_smoke(): void
    {
        $advisor = $this->makeAdvisor('advisor-stream', 'Advisor Stream');
        $owner = $this->makeClient('1000001008', 'Owner Stream');

        for ($index = 0; $index < 25; $index++) {
            $this->makeMoto($owner, $advisor, ['holder_name' => 'Stream Moto '.$index]);
            $this->makeCredit($owner, $advisor, ['holder_name' => 'Stream Credit '.$index]);
        }

        $this->grant(RoleCode::ADVISOR, PermissionKey::CANCELLATIONS_EXPORT);
        $this->actingAsReady($advisor);

        $response = $this->post(route('advisor.cancellations.export'), [
            '_token' => 'phase11-token',
            'type' => 'MOTO',
        ]);
        $binary = $this->assertDownloadResponse($response->baseResponse);
        $path = $binary->getFile()->getPathname();
        $workbook = $this->workbookRows($path);

        $this->assertStringContainsString('private', (string) $binary->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $binary->headers->get('Cache-Control'));
        $this->assertStringContainsString('attachment;', (string) $binary->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('Owner Stream', (string) $binary->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('1000001008', (string) $binary->headers->get('Content-Disposition'));
        $this->assertStringStartsWith(Storage::disk('exports')->path('cancellations'), $path);
        $this->assertCount(26, $workbook['Moto']);
        $this->assertSame(['Moto'], array_keys($workbook));

        $audit = Audit::query()->where('event_type', AuditEventType::EXPORT_GENERATED)->firstOrFail();
        $this->assertSame(25, $audit->metadata['row_count']);
        $this->assertStringNotContainsString($path, json_encode($audit->metadata, JSON_THROW_ON_ERROR));
    }

    private function grant(RoleCode $roleCode, PermissionKey $permissionKey): void
    {
        app(GrantRolePermissionAction::class)->execute(
            Role::query()->where('code', $roleCode->value)->firstOrFail(),
            $permissionKey,
        );
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

    private function makeAdvisor(string $username, string $name): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::ADVISOR),
            'username' => $username,
            'identity' => null,
            'name' => $name,
            'email' => $username.'@example.test',
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
        $timestamps = $this->extractTimestamps($overrides);

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

        $this->applyTimestamps('moto_cancellations', $moto->id, $timestamps);

        return $moto->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCredit(User $owner, User $creator, array $overrides = []): CreditCancellation
    {
        $timestamps = $this->extractTimestamps($overrides);

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

        $this->applyTimestamps('credit_cancellations', $credit->id, $timestamps);

        return $credit->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{created_at?: string, updated_at?: string}
     */
    private function extractTimestamps(array &$overrides): array
    {
        $timestamps = [];

        foreach (['created_at', 'updated_at'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $timestamps[$key] = (string) $overrides[$key];
                unset($overrides[$key]);
            }
        }

        return $timestamps;
    }

    /**
     * @param  array{created_at?: string, updated_at?: string}  $timestamps
     */
    private function applyTimestamps(string $table, int $id, array $timestamps): void
    {
        if ($timestamps === []) {
            return;
        }

        DB::table($table)
            ->where('id', $id)
            ->update($timestamps);
    }

    private function makeOtpChallenge(OtpPurpose $purpose): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase11-'.bin2hex(random_bytes(8)),
            'purpose' => $purpose,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase11'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }

    private function createMotoResponse(MotoCancellation $moto, User $advisor, string $date, string $observation): void
    {
        $moto->forceFill([
            'status' => CancellationStatus::RESPUESTA_OBTENIDA,
            'version' => $moto->version + 1,
        ])->save();

        CancellationResponse::query()->create([
            'moto_cancellation_id' => $moto->id,
            'cancellation_date' => CarbonImmutable::parse($date),
            'observation' => $observation,
            'created_by_user_id' => $advisor->id,
        ]);
    }

    private function createCreditResponse(CreditCancellation $credit, User $advisor, string $date, string $observation): void
    {
        $credit->forceFill([
            'status' => CancellationStatus::RESPUESTA_OBTENIDA,
            'version' => $credit->version + 1,
        ])->save();

        CancellationResponse::query()->create([
            'credit_cancellation_id' => $credit->id,
            'cancellation_date' => CarbonImmutable::parse($date),
            'observation' => $observation,
            'created_by_user_id' => $advisor->id,
        ]);
    }

    private function actingAsReady(User $user): void
    {
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
                '_token' => 'phase11-token',
            ]);
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function workbookRows(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            $sheets = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                $rows = [];

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(
                        static fn ($cell): mixed => $cell->getValue(),
                        $row->getCells(),
                    );
                }

                $sheets[$sheet->getName()] = $rows;
            }

            return $sheets;
        } finally {
            $reader->close();
        }
    }

    private function downloadPath(mixed $response): string
    {
        return $this->assertDownloadResponse($response)->getFile()->getPathname();
    }

    private function assertDownloadResponse(mixed $response): BinaryFileResponse
    {
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringEndsWith('.xlsx', $response->getFile()->getFilename());

        return $response;
    }

    /**
     * @param  list<mixed>  $headers
     */
    private function assertContainsNoInternalColumns(array $headers): void
    {
        $normalizedHeaders = array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            $headers,
        );

        foreach (['id', 'version', 'origin', 'audit', 'activity', 'sms', 'notification'] as $forbidden) {
            $this->assertNotContains($forbidden, $normalizedHeaders);
        }
    }
}
