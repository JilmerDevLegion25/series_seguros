<?php

namespace App\Actions\Exports;

use App\DTOs\Cancellations\CancellationSearchFilters;
use App\DTOs\Exports\CancellationExportResult;
use App\Enums\AuditEventType;
use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Exceptions\CancellationExportException;
use App\Models\Audit;
use App\Models\User;
use App\Queries\CancellationExportQuery;
use App\Services\Spreadsheet\CancellationExportWriter;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class GenerateCancellationExportAction
{
    private const MOTO_HEADERS = [
        'Creación',
        'Placa',
        'Nombre y Apellidos (Tarjeta de Propiedad)',
        'Cédula',
        'Celular',
        'Correo Electrónico',
        'Motivo Cancelación',
        '¿Quién le brindó la información de cancelación?',
        '¿Usted es el titular del crédito?',
        'Nombres y apellidos (Dueño del Crédito)',
        'Cédula',
        'Radicado',
        '¿La motocicleta tiene limitación a la propiedad (prenda o pignoración), con ADMINISTRACIÓN E INVERSIONES ADEINCO? (Revisar parte de atrás de la tarjeta de propiedad)',
    ];

    private const CREDIT_HEADERS = [
        'Creación',
        'Cédula',
        'Nombre y apellidos (Titular del crédito)',
        'Número Crédito',
        'Celular',
        'Correo Electrónico',
        'Cancelar Seguro de Accidentes Personales',
        'Cancelar Seguro de Desempleo',
        'Motivo de cancelación',
        'Quién le brindó la información de cancelación?',
        'Radicado',
    ];

    public function __construct(
        private CancellationExportQuery $query,
        private CancellationExportWriter $writer,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws CancellationExportException
     */
    public function execute(CancellationSearchFilters $filters, User $actor, string $requestId): CancellationExportResult
    {
        $this->authorize($actor);
        $sheetName = $this->selectedSheetName($filters);

        $rowCount = $this->query->count($filters);
        $maxRows = (int) config('exports.max_rows', 5000);

        if ($rowCount > $maxRows) {
            throw CancellationExportException::rowLimitExceeded($maxRows);
        }

        $diskName = (string) config('exports.disk', 'exports');
        $disk = Storage::disk($diskName);
        $filename = 'cancelaciones-'.now()->format('YmdHis').'-'.Str::lower(Str::random(12)).'.xlsx';
        $storedPath = 'cancellations/'.$filename;

        $disk->makeDirectory('cancellations');
        $absolutePath = $disk->path($storedPath);

        try {
            $this->writer->writeSheets($absolutePath, [
                $sheetName => $this->selectedSheetRows($filters),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($storedPath);

            throw CancellationExportException::writeFailed($exception);
        }

        Audit::query()->create([
            'moto_cancellation_id' => null,
            'credit_cancellation_id' => null,
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::EXPORT_GENERATED,
            'request_id' => $requestId,
            'metadata' => [
                'filename' => $filename,
                'row_count' => $rowCount,
                'sheets' => [$sheetName],
                'filters' => $this->filterMetadata($filters),
            ],
        ]);

        return new CancellationExportResult(
            storedPath: $storedPath,
            absolutePath: $absolutePath,
            filename: $filename,
            rowCount: $rowCount,
        );
    }

    /**
     * @throws CancellationExportException
     */
    private function selectedSheetName(CancellationSearchFilters $filters): string
    {
        return match ($filters->type) {
            CancellationType::MOTO => 'Moto',
            CancellationType::CREDIT => 'Credit',
            default => throw CancellationExportException::missingCancellationType(),
        };
    }

    /**
     * @return iterable<int, list<string>>
     *
     * @throws CancellationExportException
     */
    private function selectedSheetRows(CancellationSearchFilters $filters): iterable
    {
        return match ($filters->type) {
            CancellationType::MOTO => $this->motoSheetRows($filters),
            CancellationType::CREDIT => $this->creditSheetRows($filters),
            default => throw CancellationExportException::missingCancellationType(),
        };
    }

    /**
     * @return iterable<int, list<string>>
     */
    private function motoSheetRows(CancellationSearchFilters $filters): iterable
    {
        yield self::MOTO_HEADERS;

        foreach ($this->query->motoRows($filters) as $row) {
            yield [
                $this->dateTimeText($row->created_at),
                $this->safeText($row->plate),
                $this->safeText($row->holder_name),
                $this->safeText($row->holder_cedula),
                $this->safeText(preg_replace('/^\+57/', '', $row->holder_phone)),
                $this->safeText($row->holder_email),
                $this->safeText($row->cancellation_reason),
                $this->safeText($row->cancellation_information_source),
                $this->yesNo($row->is_credit_holder),
                $this->safeText($row->credit_owner_name),
                $this->safeText($row->credit_owner_cedula),
                $this->safeText($row->radicado),
                $this->yesNo($row->property_lien_adeinco),
            ];
        }
    }

    /**
     * @return iterable<int, list<string>>
     */
    private function creditSheetRows(CancellationSearchFilters $filters): iterable
    {
        yield self::CREDIT_HEADERS;

        foreach ($this->query->creditRows($filters) as $row) {
            yield [
                $this->dateTimeText($row->created_at),
                $this->safeText($row->holder_cedula),
                $this->safeText($row->holder_name),
                $this->safeText($row->credit_number),
                $this->safeText(preg_replace('/^\+57/', '', $row->holder_phone)),
                $this->safeText($row->holder_email),
                $this->yesNo($row->cancel_personal_accidents),
                $this->yesNo($row->cancel_unemployment_insurance),
                $this->safeText($row->cancellation_reason),
                $this->safeText($row->cancellation_information_source),
                $this->safeText($row->radicado),
            ];
        }
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function filterMetadata(CancellationSearchFilters $filters): array
    {
        return [
            'type' => $filters->type instanceof CancellationType ? $filters->type->value : 'ALL',
            'status' => $filters->status?->value,
            'radicado' => $filters->radicado,
            'holder_name' => $this->maskString($filters->holderName),
            'holder_cedula' => $this->maskString($filters->holderCedula),
            'holder_email' => $this->maskString($filters->holderEmail),
            'holder_phone' => $this->maskString($filters->holderPhone),
            'plate' => $filters->plate,
            'credit_number' => $filters->creditNumber,
            'assigned_advisor_user_id' => $filters->assignedAdvisorUserId,
            'created_from' => $filters->createdFrom?->toDateString(),
            'created_to' => $filters->createdTo?->toDateString(),
            'sort' => $filters->sort,
        ];
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $actor): void
    {
        if (! $actor->isAdvisor() || ! $actor->can(PermissionKey::CANCELLATIONS_EXPORT->value)) {
            throw new AuthorizationException;
        }
    }

    private function yesNo(mixed $value): string
    {
        return ((bool) $value) ? 'SI' : 'NO';
    }

    private function dateTimeText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return CarbonImmutable::parse((string) $value)->format('Y-m-d H:i:s');
    }

    private function safeText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = (string) $value;

        return preg_match('/^\s*[=\+\-@]/', $text) === 1 ? "'".$text : $text;
    }

    private function maskString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $length = strlen($value);

        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(0, $length - 2)).substr($value, -2);
    }
}
