<?php

namespace App\Actions\Imports;

use App\Actions\Cancellations\Concerns\AuthorizesCancellationMutations;
use App\DTOs\Imports\ParsedResponseImportRow;
use App\DTOs\Imports\ResponseImportData;
use App\DTOs\Imports\ResponseImportResult;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowReason;
use App\Enums\ImportRowStatus;
use App\Enums\NotificationType;
use App\Enums\PermissionKey;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\ImportBatch;
use App\Models\ImportRowResult;
use App\Models\MotoCancellation;
use App\Models\Notification;
use App\Models\Response as CancellationResponse;
use App\Models\User;
use App\Services\Spreadsheet\ResponseImportReader;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessResponseImportAction
{
    use AuthorizesCancellationMutations;

    public function __construct(
        private readonly ResponseImportReader $reader,
    ) {}

    public function execute(ResponseImportData $data, User $actor, ?string $requestId = null): ResponseImportResult
    {
        $this->assertAdvisorCan($actor, PermissionKey::RESPONSES_IMPORT);

        $batch = ImportBatch::query()->create([
            'uploaded_by_user_id' => $actor->id,
            'cancellation_type' => $data->type,
            'status' => ImportBatchStatus::PROCESSING,
            'stored_path' => $data->storedPath,
            'total_rows' => 0,
            'successful_rows' => 0,
            'rejected_rows' => 0,
        ]);

        $rows = $this->readRows($batch, $data->storedPath);
        $freshBatch = $batch->fresh();

        if ($freshBatch instanceof ImportBatch && $freshBatch->status === ImportBatchStatus::FAILED) {
            return new ResponseImportResult($freshBatch);
        }

        foreach ($this->markDuplicates($rows) as $row) {
            if (! $row->isEligible()) {
                $this->recordRejectedRow($batch, $row, $row->rejectionReason ?? ImportRowReason::INVALID_ROW);

                continue;
            }

            if ($data->type === CancellationType::MOTO) {
                $this->processMotoRow($batch, $row, $actor, $requestId);
            } else {
                $this->processCreditRow($batch, $row, $actor, $requestId);
            }
        }

        $this->completeBatch($batch);

        return new ResponseImportResult($batch->fresh() ?? $batch);
    }

    /**
     * @return list<ParsedResponseImportRow>
     */
    private function readRows(ImportBatch $batch, string $storedPath): array
    {
        $disk = (string) config('imports.disk', 'imports');
        $path = Storage::disk($disk)->path($storedPath);
        $headerMap = null;
        $rows = [];
        $lineNumber = 0;

        try {
            foreach ($this->reader->rows($path) as $rawRow) {
                $lineNumber++;

                if ($lineNumber === 1) {
                    $headerMap = $this->headerMap($rawRow);

                    if ($headerMap === null) {
                        $this->failBatch($batch, 'INVALID_HEADER');

                        return [];
                    }

                    continue;
                }

                if (count($rows) >= (int) config('imports.max_rows')) {
                    $this->failBatch($batch, 'ROW_LIMIT_EXCEEDED');

                    return [];
                }

                $rows[] = $this->parseRow($lineNumber, $rawRow, $headerMap);
            }
        } catch (Throwable) {
            $this->failBatch($batch, 'READ_ERROR');

            return [];
        }

        if ($headerMap === null) {
            $this->failBatch($batch, 'INVALID_HEADER');

            return [];
        }

        return $rows;
    }

    /**
     * @param  list<mixed>  $header
     * @return array{radicado: int, cancellation_date: int, observation: int}|null
     */
    private function headerMap(array $header): ?array
    {
        $map = [];

        foreach ($header as $index => $cell) {
            $normalized = $this->normalizeHeader((string) $cell);

            if ($normalized === 'radicado') {
                $map['radicado'] = $index;
            } elseif (str_starts_with($normalized, 'fecha cancelaci')) {
                $map['cancellation_date'] = $index;
            } elseif (str_starts_with($normalized, 'observaci')) {
                $map['observation'] = $index;
            }
        }

        if (
            ! isset($map['radicado'])
            || ! isset($map['cancellation_date'])
            || ! isset($map['observation'])
        ) {
            return null;
        }

        return [
            'radicado' => $map['radicado'],
            'cancellation_date' => $map['cancellation_date'],
            'observation' => $map['observation'],
        ];
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);

        return $value ?? '';
    }

    /**
     * @param  list<mixed>  $row
     * @param  array{radicado: int, cancellation_date: int, observation: int}  $headerMap
     */
    private function parseRow(int $rowNumber, array $row, array $headerMap): ParsedResponseImportRow
    {
        $radicado = $this->parseRadicado($this->cell($row, $headerMap['radicado']));
        $date = $this->parseDate($this->cell($row, $headerMap['cancellation_date']));
        $observation = $this->parseObservation($this->cell($row, $headerMap['observation']));
        $reason = null;

        if ($radicado === null) {
            $reason = ImportRowReason::INVALID_RADICADO;
        } elseif ($date === null) {
            $reason = ImportRowReason::INVALID_DATE;
        } elseif ($date->isFuture()) {
            $reason = ImportRowReason::FUTURE_DATE;
        } elseif ($observation === null) {
            $reason = ImportRowReason::MISSING_OBSERVATION;
        } elseif (mb_strlen($observation) > 2000) {
            $reason = ImportRowReason::OBSERVATION_TOO_LONG;
        }

        return new ParsedResponseImportRow(
            rowNumber: $rowNumber,
            radicado: $radicado,
            cancellationDate: $date,
            observation: $observation,
            rejectionReason: $reason,
        );
    }

    /**
     * @param  list<mixed>  $row
     */
    private function cell(array $row, int $index): mixed
    {
        return $row[$index] ?? null;
    }

    private function parseRadicado(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_float($value) && $value > 0 && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::parse($value->format('Y-m-d'))->startOfDay();
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if (
                $parsed instanceof DateTimeImmutable
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $parsed->format($format) === $value
            ) {
                return CarbonImmutable::parse($parsed->format('Y-m-d'))->startOfDay();
            }
        }

        return null;
    }

    private function parseObservation(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $observation = trim((string) $value);

        return $observation === '' ? null : $observation;
    }

    /**
     * @param  list<ParsedResponseImportRow>  $rows
     * @return list<ParsedResponseImportRow>
     */
    private function markDuplicates(array $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            if ($row->radicado !== null) {
                $counts[$row->radicado] = ($counts[$row->radicado] ?? 0) + 1;
            }
        }

        return array_map(
            static fn (ParsedResponseImportRow $row): ParsedResponseImportRow => $row->radicado !== null && ($counts[$row->radicado] ?? 0) > 1
                ? $row->withRejection(ImportRowReason::DUPLICATE_IN_FILE)
                : $row,
            $rows,
        );
    }

    private function processMotoRow(ImportBatch $batch, ParsedResponseImportRow $row, User $actor, ?string $requestId): void
    {
        DB::transaction(function () use ($batch, $row, $actor, $requestId): void {
            /** @var MotoCancellation|null $moto */
            $moto = MotoCancellation::query()
                ->where('radicado', $row->radicado)
                ->lockForUpdate()
                ->first();

            if (! $moto instanceof MotoCancellation) {
                $this->recordRejectedRow($batch, $row, ImportRowReason::RADICADO_NOT_AVAILABLE);

                return;
            }

            if ($moto->radicado === null || $moto->status === CancellationStatus::PENDIENTE_RADICACION) {
                $this->recordRejectedRow($batch, $row, ImportRowReason::RADICADO_NOT_AVAILABLE, moto: $moto);

                return;
            }

            if ($moto->status === CancellationStatus::RESPUESTA_OBTENIDA || $moto->response()->exists()) {
                $this->recordRejectedRow($batch, $row, ImportRowReason::ALREADY_RESPONDED, moto: $moto);

                return;
            }

            $moto->forceFill([
                'status' => CancellationStatus::RESPUESTA_OBTENIDA,
                'version' => $moto->version + 1,
            ])->save();

            $response = $this->createMotoResponse($moto, $row, $actor);
            $this->createMotoNotification($moto);
            $this->recordMotoSuccess($batch, $row, $actor, $requestId, $moto, $response);
        });
    }

    private function processCreditRow(ImportBatch $batch, ParsedResponseImportRow $row, User $actor, ?string $requestId): void
    {
        DB::transaction(function () use ($batch, $row, $actor, $requestId): void {
            /** @var CreditCancellation|null $credit */
            $credit = CreditCancellation::query()
                ->where('radicado', $row->radicado)
                ->lockForUpdate()
                ->first();

            if (! $credit instanceof CreditCancellation) {
                $this->recordRejectedRow($batch, $row, ImportRowReason::RADICADO_NOT_AVAILABLE);

                return;
            }

            if ($credit->status === CancellationStatus::RESPUESTA_OBTENIDA || $credit->response()->exists()) {
                $this->recordRejectedRow($batch, $row, ImportRowReason::ALREADY_RESPONDED, credit: $credit);

                return;
            }

            $credit->forceFill([
                'status' => CancellationStatus::RESPUESTA_OBTENIDA,
                'version' => $credit->version + 1,
            ])->save();

            $response = $this->createCreditResponse($credit, $row, $actor);
            $this->createCreditNotification($credit);
            $this->recordCreditSuccess($batch, $row, $actor, $requestId, $credit, $response);
        });
    }

    private function createMotoResponse(MotoCancellation $moto, ParsedResponseImportRow $row, User $actor): CancellationResponse
    {
        /** @var CancellationResponse $response */
        $response = CancellationResponse::query()->create([
            'moto_cancellation_id' => $moto->id,
            'cancellation_date' => $row->cancellationDate,
            'observation' => $row->observation,
            'created_by_user_id' => $actor->id,
        ]);

        return $response;
    }

    private function createCreditResponse(CreditCancellation $credit, ParsedResponseImportRow $row, User $actor): CancellationResponse
    {
        /** @var CancellationResponse $response */
        $response = CancellationResponse::query()->create([
            'credit_cancellation_id' => $credit->id,
            'cancellation_date' => $row->cancellationDate,
            'observation' => $row->observation,
            'created_by_user_id' => $actor->id,
        ]);

        return $response;
    }

    private function createMotoNotification(MotoCancellation $moto): void
    {
        Notification::query()->firstOrCreate(
            [
                'moto_cancellation_id' => $moto->id,
                'type' => NotificationType::RESPONSE_OBTAINED,
            ],
            [
                'user_id' => $moto->owner_user_id,
            ],
        );
    }

    private function createCreditNotification(CreditCancellation $credit): void
    {
        Notification::query()->firstOrCreate(
            [
                'credit_cancellation_id' => $credit->id,
                'type' => NotificationType::RESPONSE_OBTAINED,
            ],
            [
                'user_id' => $credit->owner_user_id,
            ],
        );
    }

    private function recordMotoSuccess(
        ImportBatch $batch,
        ParsedResponseImportRow $row,
        User $actor,
        ?string $requestId,
        MotoCancellation $moto,
        CancellationResponse $response,
    ): void {
        $radicado = (int) $moto->radicado;

        Activity::query()->create([
            'moto_cancellation_id' => $moto->id,
            'actor_user_id' => $actor->id,
            'type' => ActivityType::RESPONSE_OBTAINED,
            'metadata' => $this->successMetadata(CancellationType::MOTO, $radicado, $moto->version, $response, $batch, $row),
        ]);

        Audit::query()->create([
            'moto_cancellation_id' => $moto->id,
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::RESPONSE_OBTAINED,
            'request_id' => $requestId,
            'metadata' => $this->successMetadata(CancellationType::MOTO, $radicado, $moto->version, $response, $batch, $row),
        ]);

        $this->recordSuccessfulRow($batch, $row, moto: $moto, response: $response);
    }

    private function recordCreditSuccess(
        ImportBatch $batch,
        ParsedResponseImportRow $row,
        User $actor,
        ?string $requestId,
        CreditCancellation $credit,
        CancellationResponse $response,
    ): void {
        Activity::query()->create([
            'credit_cancellation_id' => $credit->id,
            'actor_user_id' => $actor->id,
            'type' => ActivityType::RESPONSE_OBTAINED,
            'metadata' => $this->successMetadata(CancellationType::CREDIT, $credit->radicado, $credit->version, $response, $batch, $row),
        ]);

        Audit::query()->create([
            'credit_cancellation_id' => $credit->id,
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::RESPONSE_OBTAINED,
            'request_id' => $requestId,
            'metadata' => $this->successMetadata(CancellationType::CREDIT, $credit->radicado, $credit->version, $response, $batch, $row),
        ]);

        $this->recordSuccessfulRow($batch, $row, credit: $credit, response: $response);
    }

    /**
     * @return array<string, int|string>
     */
    private function successMetadata(
        CancellationType $type,
        int $radicado,
        int $version,
        CancellationResponse $response,
        ImportBatch $batch,
        ParsedResponseImportRow $row,
    ): array {
        return [
            'event' => AuditEventType::RESPONSE_OBTAINED->value,
            'type' => $type->value,
            'radicado' => $radicado,
            'version' => $version,
            'response_id' => $response->id,
            'import_batch_id' => $batch->id,
            'import_row_number' => $row->rowNumber,
        ];
    }

    private function recordSuccessfulRow(
        ImportBatch $batch,
        ParsedResponseImportRow $row,
        ?MotoCancellation $moto = null,
        ?CreditCancellation $credit = null,
        ?CancellationResponse $response = null,
    ): void {
        ImportRowResult::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => $row->rowNumber,
            'radicado' => $row->radicado,
            'status' => ImportRowStatus::SUCCESS,
            'reason' => null,
            'message' => 'Response importada.',
            'moto_cancellation_id' => $moto?->id,
            'credit_cancellation_id' => $credit?->id,
            'response_id' => $response?->id,
        ]);
    }

    private function recordRejectedRow(
        ImportBatch $batch,
        ParsedResponseImportRow $row,
        ImportRowReason $reason,
        ?MotoCancellation $moto = null,
        ?CreditCancellation $credit = null,
    ): void {
        ImportRowResult::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => $row->rowNumber,
            'radicado' => $row->radicado,
            'status' => ImportRowStatus::REJECTED,
            'reason' => $reason,
            'message' => $this->messageFor($reason),
            'moto_cancellation_id' => $moto?->id,
            'credit_cancellation_id' => $credit?->id,
        ]);
    }

    private function messageFor(ImportRowReason $reason): string
    {
        return match ($reason) {
            ImportRowReason::DUPLICATE_IN_FILE => 'Radicado duplicado en el archivo.',
            ImportRowReason::RADICADO_NOT_AVAILABLE => 'Radicado no disponible para el producto seleccionado.',
            ImportRowReason::INVALID_RADICADO => 'Radicado invalido.',
            ImportRowReason::INVALID_DATE => 'Fecha de cancelacion invalida.',
            ImportRowReason::FUTURE_DATE => 'Fecha de cancelacion futura.',
            ImportRowReason::MISSING_OBSERVATION => 'Observacion requerida.',
            ImportRowReason::OBSERVATION_TOO_LONG => 'Observacion supera 2000 caracteres.',
            ImportRowReason::ALREADY_RESPONDED => 'La solicitud ya tiene respuesta.',
            ImportRowReason::INVALID_ROW => 'Fila invalida.',
        };
    }

    private function failBatch(ImportBatch $batch, string $reason): void
    {
        $batch->forceFill([
            'status' => ImportBatchStatus::FAILED,
            'failure_reason' => $reason,
        ])->save();
    }

    private function completeBatch(ImportBatch $batch): void
    {
        $successful = ImportRowResult::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', ImportRowStatus::SUCCESS)
            ->count();
        $rejected = ImportRowResult::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', ImportRowStatus::REJECTED)
            ->count();

        $batch->forceFill([
            'status' => $rejected > 0 ? ImportBatchStatus::COMPLETED_WITH_ERRORS : ImportBatchStatus::COMPLETED,
            'total_rows' => $successful + $rejected,
            'successful_rows' => $successful,
            'rejected_rows' => $rejected,
        ])->save();
    }
}
