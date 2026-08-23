<?php

namespace Tests\Feature\Imports;

use App\Actions\Authorization\GrantRolePermissionAction;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowReason;
use App\Enums\ImportRowStatus;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Enums\NotificationType;
use App\Enums\OtpPurpose;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\ImportBatch;
use App\Models\ImportRowResult;
use App\Models\MotoCancellation;
use App\Models\Notification;
use App\Models\OtpChallenge;
use App\Models\Response as CancellationResponse;
use App\Models\Role;
use App\Models\User;
use App\Services\Spreadsheet\OpenSpoutCancellationExportWriter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\RefreshPhaseDatabase;
use Tests\TestCase;

final class ResponseImportTest extends TestCase
{
    use RefreshPhaseDatabase {
        setUp as refreshPhaseDatabaseSetUp;
    }

    private int $nextRadicado = 3000;

    protected function setUp(): void
    {
        $this->refreshPhaseDatabaseSetUp();
        Storage::disk('imports')->deleteDirectory('responses');
    }

    public function test_moto_response_import_success_creates_terminal_response_history_and_notification(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000001');
        $moto = $this->makeMoto($owner, $advisor);
        $date = now()->subDay()->toDateTimeImmutable();
        $this->grantAdvisor(PermissionKey::RESPONSES_IMPORT);
        $this->actingAsReady($advisor);

        $this->post(route('advisor.responses.import.store'), [
            '_token' => 'phase08-token',
            'type' => 'MOTO',
            'file' => $this->xlsxUpload([
                ['Radicado', 'Fecha cancelacion', 'Observaciones', 'Placa'],
                [$moto->radicado, $date, 'Cancelacion confirmada', 'IGNORADA'],
            ], 'legacy-moto.xlsx'),
        ])->assertOk()->assertSee('COMPLETED');

        $moto->refresh();
        $response = CancellationResponse::query()->firstOrFail();
        $batch = ImportBatch::query()->firstOrFail();
        $row = ImportRowResult::query()->firstOrFail();

        $this->assertSame(CancellationStatus::RESPUESTA_OBTENIDA, $moto->status);
        $this->assertSame(2, $moto->version);
        $this->assertSame($moto->id, $response->moto_cancellation_id);
        $this->assertNull($response->credit_cancellation_id);
        $this->assertSame($date->format('Y-m-d'), $response->cancellation_date->toDateString());
        $this->assertSame('Cancelacion confirmada', $response->observation);
        $this->assertSame($advisor->id, $response->created_by_user_id);
        $this->assertSame(ImportBatchStatus::COMPLETED, $batch->status);
        $this->assertSame(1, $batch->total_rows);
        $this->assertSame(1, $batch->successful_rows);
        $this->assertSame(0, $batch->rejected_rows);
        $this->assertStringStartsWith('responses/', $batch->stored_path);
        $this->assertStringNotContainsString('legacy-moto.xlsx', $batch->stored_path);
        $this->assertTrue(Storage::disk('imports')->exists($batch->stored_path));
        $this->assertSame(ImportRowStatus::SUCCESS, $row->status);
        $this->assertNull($row->reason);
        $this->assertSame($response->id, $row->response_id);
        $this->assertSame(1, Notification::query()->where('user_id', $owner->id)->where('type', NotificationType::RESPONSE_OBTAINED)->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Activity::query()->where('type', ActivityType::RESPONSE_OBTAINED)->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::RESPONSE_OBTAINED)->where('moto_cancellation_id', $moto->id)->count());
    }

    public function test_credit_response_import_success_creates_credit_response_only(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000002');
        $credit = $this->makeCredit($owner, $advisor);
        $this->grantAdvisor(PermissionKey::RESPONSES_IMPORT);
        $this->actingAsReady($advisor);

        $this->post(route('advisor.responses.import.store'), [
            '_token' => 'phase08-token',
            'type' => 'CREDIT',
            'file' => $this->xlsxUpload([
                ['Radicado', 'Fecha cancelacion', 'Observaciones', 'Nombre'],
                [$credit->radicado, now()->subDays(2)->toDateTimeImmutable(), 'Credito finalizado', 'IGNORADO'],
            ]),
        ])->assertOk()->assertSee('COMPLETED');

        $credit->refresh();
        $response = CancellationResponse::query()->firstOrFail();

        $this->assertSame(CancellationStatus::RESPUESTA_OBTENIDA, $credit->status);
        $this->assertNull($response->moto_cancellation_id);
        $this->assertSame($credit->id, $response->credit_cancellation_id);
        $this->assertSame(1, Notification::query()->where('user_id', $owner->id)->where('credit_cancellation_id', $credit->id)->count());
        $this->assertSame(1, Activity::query()->where('type', ActivityType::RESPONSE_OBTAINED)->where('credit_cancellation_id', $credit->id)->count());
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::RESPONSE_OBTAINED)->where('credit_cancellation_id', $credit->id)->count());
    }

    public function test_mixed_import_rejects_bad_rows_and_keeps_successful_rows_committed(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000003');
        $successMoto = $this->makeMoto($owner, $advisor);
        $wrongProductCredit = $this->makeCredit($owner, $advisor);
        $terminalMoto = $this->makeMoto($owner, $advisor);
        $duplicateMoto = $this->makeMoto($owner, $advisor);
        $this->makeTerminalResponse($terminalMoto, $advisor);
        $this->grantAdvisor(PermissionKey::RESPONSES_IMPORT);
        $this->actingAsReady($advisor);

        $this->post(route('advisor.responses.import.store'), [
            '_token' => 'phase08-token',
            'type' => 'MOTO',
            'file' => $this->xlsxUpload([
                ['Radicado', 'Fecha cancelacion', 'Observaciones', 'Cedula'],
                [$successMoto->radicado, now()->subDay()->toDateTimeImmutable(), 'Fila exitosa', 'IGNORADA'],
                [$wrongProductCredit->radicado, now()->subDay()->toDateTimeImmutable(), 'Producto incorrecto', 'IGNORADA'],
                [999999, now()->subDay()->toDateTimeImmutable(), 'No existe', 'IGNORADA'],
                ['ABC', now()->subDay()->toDateTimeImmutable(), 'Radicado malo', 'IGNORADA'],
                [$this->nextRadicado++, 'fecha mala', 'Fecha mala', 'IGNORADA'],
                [$this->nextRadicado++, now()->addDay()->toDateTimeImmutable(), 'Fecha futura', 'IGNORADA'],
                [$this->nextRadicado++, now()->subDay()->toDateTimeImmutable(), ' ', 'IGNORADA'],
                [$this->nextRadicado++, now()->subDay()->toDateTimeImmutable(), str_repeat('A', 2001), 'IGNORADA'],
                [$duplicateMoto->radicado, now()->subDay()->toDateTimeImmutable(), 'Duplicada 1', 'IGNORADA'],
                [$duplicateMoto->radicado, now()->subDay()->toDateTimeImmutable(), 'Duplicada 2', 'IGNORADA'],
                [$terminalMoto->radicado, now()->subDay()->toDateTimeImmutable(), 'Terminal', 'IGNORADA'],
            ]),
        ])->assertOk()->assertSee('COMPLETED_WITH_ERRORS');

        $successMoto->refresh();
        $wrongProductCredit->refresh();
        $terminalMoto->refresh();
        $duplicateMoto->refresh();
        $batch = ImportBatch::query()->latest('id')->firstOrFail();

        $this->assertSame(CancellationStatus::RESPUESTA_OBTENIDA, $successMoto->status);
        $this->assertSame(2, $successMoto->version);
        $this->assertSame(CancellationStatus::EN_GESTION, $wrongProductCredit->status);
        $this->assertSame(CancellationStatus::RESPUESTA_OBTENIDA, $terminalMoto->status);
        $this->assertSame(2, $terminalMoto->version);
        $this->assertSame(CancellationStatus::EN_GESTION, $duplicateMoto->status);
        $this->assertSame(2, CancellationResponse::query()->count());
        $this->assertSame(ImportBatchStatus::COMPLETED_WITH_ERRORS, $batch->status);
        $this->assertSame(11, $batch->total_rows);
        $this->assertSame(1, $batch->successful_rows);
        $this->assertSame(10, $batch->rejected_rows);
        $this->assertReasonCount(ImportRowReason::RADICADO_NOT_AVAILABLE, 2);
        $this->assertReasonCount(ImportRowReason::INVALID_RADICADO, 1);
        $this->assertReasonCount(ImportRowReason::INVALID_DATE, 1);
        $this->assertReasonCount(ImportRowReason::FUTURE_DATE, 1);
        $this->assertReasonCount(ImportRowReason::MISSING_OBSERVATION, 1);
        $this->assertReasonCount(ImportRowReason::OBSERVATION_TOO_LONG, 1);
        $this->assertReasonCount(ImportRowReason::DUPLICATE_IN_FILE, 2);
        $this->assertReasonCount(ImportRowReason::ALREADY_RESPONDED, 1);
        $this->assertSame(1, Activity::query()->where('type', ActivityType::RESPONSE_OBTAINED)->where('moto_cancellation_id', $successMoto->id)->count());
        $this->assertSame(1, Notification::query()->where('moto_cancellation_id', $successMoto->id)->count());
    }

    public function test_header_validation_fails_before_domain_mutation(): void
    {
        $advisor = $this->makeAdvisor();
        $moto = $this->makeMoto($this->makeClient('1000000004'), $advisor);
        $this->grantAdvisor(PermissionKey::RESPONSES_IMPORT);
        $this->actingAsReady($advisor);

        $this->post(route('advisor.responses.import.store'), [
            '_token' => 'phase08-token',
            'type' => 'MOTO',
            'file' => $this->xlsxUpload([
                ['Placa', 'Fecha cancelacion', 'Observaciones'],
                [$moto->plate, now()->subDay()->toDateTimeImmutable(), 'No debe importar'],
            ]),
        ])->assertOk()->assertSee('FAILED');

        $moto->refresh();
        $batch = ImportBatch::query()->firstOrFail();

        $this->assertSame(CancellationStatus::EN_GESTION, $moto->status);
        $this->assertSame(1, $moto->version);
        $this->assertSame(0, CancellationResponse::query()->count());
        $this->assertSame(0, ImportRowResult::query()->count());
        $this->assertSame(ImportBatchStatus::FAILED, $batch->status);
        $this->assertSame('INVALID_HEADER', $batch->failure_reason);
    }

    public function test_permission_and_upload_security_are_enforced(): void
    {
        $advisor = $this->makeAdvisor();
        $this->actingAsReady($advisor);

        $this->get(route('advisor.responses.import.template'))
            ->assertForbidden();

        $this->post(route('advisor.responses.import.store'), [
            '_token' => 'phase08-token',
            'type' => 'MOTO',
            'file' => $this->xlsxUpload([
                ['Radicado', 'Fecha cancelacion', 'Observaciones'],
                [123, now()->subDay()->toDateTimeImmutable(), 'Sin permiso'],
            ]),
        ])->assertForbidden();

        $this->assertSame(0, ImportBatch::query()->count());

        $this->grantAdvisor(PermissionKey::RESPONSES_IMPORT);

        $this->from(route('advisor.responses.import.create'))
            ->post(route('advisor.responses.import.store'), [
                '_token' => 'phase08-token',
                'type' => 'MOTO',
                'file' => UploadedFile::fake()->create('responses.csv', 1, 'text/csv'),
            ])
            ->assertRedirect(route('advisor.responses.import.create', absolute: false))
            ->assertSessionHasErrors(['file']);

        $this->assertSame(0, ImportBatch::query()->count());
    }

    public function test_import_view_explains_headers_and_template_download_contains_contract_headers(): void
    {
        $advisor = $this->makeAdvisor();
        $this->grantAdvisor(PermissionKey::RESPONSES_IMPORT);
        $this->actingAsReady($advisor);

        $this->get(route('advisor.responses.import.create'))
            ->assertOk()
            ->assertSee('Cabeceras esperadas por el importador')
            ->assertSee('Radicado')
            ->assertSee('Fecha cancelacion')
            ->assertSee('Observaciones')
            ->assertSee('Descargar plantilla');

        $response = $this->get(route('advisor.responses.import.template'));
        $binary = $this->assertDownloadResponse($response->baseResponse);
        $workbook = $this->workbookRows($binary->getFile()->getPathname());

        $this->assertSame(['Respuestas'], array_keys($workbook));
        $this->assertSame([
            'Radicado',
            'Placa',
            'Nombre',
            'Cedula',
            'Fecha cancelacion',
            'Observaciones',
        ], $workbook['Respuestas'][0]);
        $this->assertCount(1, $workbook['Respuestas']);
        $this->assertStringContainsString('private', (string) $binary->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $binary->headers->get('Cache-Control'));
    }

    public function test_retry_same_file_does_not_duplicate_response_or_notification(): void
    {
        $advisor = $this->makeAdvisor();
        $owner = $this->makeClient('1000000005');
        $moto = $this->makeMoto($owner, $advisor);
        $this->grantAdvisor(PermissionKey::RESPONSES_IMPORT);
        $this->actingAsReady($advisor);
        $rows = [
            ['Radicado', 'Fecha cancelacion', 'Observaciones'],
            [$moto->radicado, now()->subDay()->toDateTimeImmutable(), 'Primera respuesta'],
        ];

        $this->post(route('advisor.responses.import.store'), [
            '_token' => 'phase08-token',
            'type' => 'MOTO',
            'file' => $this->xlsxUpload($rows),
        ])->assertOk();
        $this->post(route('advisor.responses.import.store'), [
            '_token' => 'phase08-token',
            'type' => 'MOTO',
            'file' => $this->xlsxUpload($rows),
        ])->assertOk()->assertSee('ALREADY_RESPONDED');

        $moto->refresh();

        $this->assertSame(CancellationStatus::RESPUESTA_OBTENIDA, $moto->status);
        $this->assertSame(2, $moto->version);
        $this->assertSame(1, CancellationResponse::query()->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Notification::query()->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Activity::query()->where('type', ActivityType::RESPONSE_OBTAINED)->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, Audit::query()->where('event_type', AuditEventType::RESPONSE_OBTAINED)->where('moto_cancellation_id', $moto->id)->count());
        $this->assertSame(1, ImportRowResult::query()->where('reason', ImportRowReason::ALREADY_RESPONDED)->count());
    }

    public function test_response_unique_constraint_prevents_second_response_for_same_cancellation(): void
    {
        $advisor = $this->makeAdvisor();
        $moto = $this->makeMoto($this->makeClient('1000000006'), $advisor);

        CancellationResponse::query()->create([
            'moto_cancellation_id' => $moto->id,
            'cancellation_date' => now()->subDay(),
            'observation' => 'Primera respuesta',
            'created_by_user_id' => $advisor->id,
        ]);

        $this->expectException(QueryException::class);

        CancellationResponse::query()->create([
            'moto_cancellation_id' => $moto->id,
            'cancellation_date' => now()->subDay(),
            'observation' => 'Segunda respuesta',
            'created_by_user_id' => $advisor->id,
        ]);
    }

    private function assertReasonCount(ImportRowReason $reason, int $expected): void
    {
        $this->assertSame($expected, ImportRowResult::query()->where('reason', $reason)->count(), $reason->value);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function xlsxUpload(array $rows, string $clientName = 'responses.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'phase08-import-');
        $this->assertIsString($path);
        @unlink($path);
        $xlsxPath = $path.'.xlsx';

        (new OpenSpoutCancellationExportWriter)->write($xlsxPath, $rows);

        return new UploadedFile(
            $xlsxPath,
            $clientName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function makeTerminalResponse(MotoCancellation $moto, User $advisor): void
    {
        $moto->forceFill([
            'status' => CancellationStatus::RESPUESTA_OBTENIDA,
            'version' => $moto->version + 1,
        ])->save();

        CancellationResponse::query()->create([
            'moto_cancellation_id' => $moto->id,
            'cancellation_date' => now()->subDays(3),
            'observation' => 'Respuesta previa',
            'created_by_user_id' => $advisor->id,
        ]);
    }

    private function grantAdvisor(PermissionKey $permissionKey): void
    {
        app(GrantRolePermissionAction::class)->execute(
            Role::query()->where('code', RoleCode::ADVISOR->value)->firstOrFail(),
            $permissionKey,
        );
    }

    private function makeAdvisor(): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::ADVISOR),
            'username' => 'ADVISOR'.bin2hex(random_bytes(3)),
            'identity' => null,
            'name' => 'Advisor User',
            'email' => 'advisor@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function makeClient(string $identity): User
    {
        return User::query()->create([
            'role_id' => Role::idFor(RoleCode::CLIENT),
            'username' => $identity,
            'identity' => $identity,
            'name' => 'Client '.$identity,
            'email' => $identity.'@example.test',
            'phone' => '+573001234567',
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'must_change_password' => false,
        ]);
    }

    private function makeMoto(User $owner, User $creator): MotoCancellation
    {
        return MotoCancellation::query()->create([
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
        ]);
    }

    private function makeCredit(User $owner, User $creator): CreditCancellation
    {
        return CreditCancellation::query()->create([
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
        ]);
    }

    private function makeOtpChallenge(OtpPurpose $purpose): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'public_reference' => 'phase08-'.bin2hex(random_bytes(8)),
            'purpose' => $purpose,
            'target_user_id' => null,
            'destination_snapshot' => '+573001234567',
            'encrypted_payload' => null,
            'otp_mac' => hash('sha256', 'phase08'),
            'failed_attempts' => 0,
            'emission_count' => 1,
            'last_emitted_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ]);
    }

    private function actingAsReady(User $user): void
    {
        $this->actingAs($user)
            ->withSession([
                'auth_started_at' => now()->timestamp,
                'auth_last_activity_at' => now()->timestamp,
                '_token' => 'phase08-token',
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

    private function assertDownloadResponse(mixed $response): BinaryFileResponse
    {
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringEndsWith('.xlsx', $response->getFile()->getFilename());

        return $response;
    }
}
