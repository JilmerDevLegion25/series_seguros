<?php

namespace App\Support\History;

use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Support\Security\SensitiveValueSanitizer;

final readonly class HistoryMetadataFormatter
{
    private const METADATA_LABELS = [
        'event' => 'Evento',
        'type' => 'Producto',
        'radicado' => 'Radicado',
        'origin' => 'Origen',
        'reason' => 'Razon',
        'version' => 'Version',
        'changed_fields' => 'Campos modificados',
        'changes' => 'Cambios',
        'sms_attempt_id' => 'Intento SMS',
        'purpose' => 'Proposito',
        'destination_snapshot' => 'Destino',
        'response_id' => 'Respuesta',
        'import_batch_id' => 'Lote importacion',
        'import_row_number' => 'Fila importacion',
        'filename' => 'Archivo',
        'row_count' => 'Filas',
        'sheets' => 'Hojas',
        'filters' => 'Filtros',
    ];

    public function activityLabel(ActivityType $type): string
    {
        return match ($type) {
            ActivityType::CREATED => 'Creada',
            ActivityType::UPDATED => 'Actualizada',
            ActivityType::OWNER_REASSIGNED => 'Advisor reasignado',
            ActivityType::RESPONSE_OBTAINED => 'Respuesta obtenida',
        };
    }

    public function auditLabel(AuditEventType $eventType): string
    {
        return match ($eventType) {
            AuditEventType::CANCELLATION_CREATED => 'Solicitud creada',
            AuditEventType::CANCELLATION_UPDATED => 'Solicitud actualizada',
            AuditEventType::OWNER_REASSIGNED => 'Advisor reasignado',
            AuditEventType::RADICADO_SMS_RETRY_REQUESTED => 'Reintento SMS radicado',
            AuditEventType::RESPONSE_OBTAINED => 'Respuesta obtenida',
            AuditEventType::EXPORT_GENERATED => 'Exportacion XLSX generada',
        };
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return list<array{label: string, value: string}>
     */
    public function format(?array $metadata): array
    {
        if ($metadata === null) {
            return [];
        }

        $formatted = [];

        foreach (self::METADATA_LABELS as $key => $label) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $this->formatValue($key, $metadata[$key]);

            if ($value === '') {
                continue;
            }

            $formatted[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $formatted;
    }

    private function formatValue(string $key, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'SI' : 'NO';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $key === 'reason'
                ? SensitiveValueSanitizer::sanitizeFreeText($value)
                : $this->safeScalarText($value);
        }

        if (is_array($value)) {
            return $key === 'changes'
                ? $this->formatChanges($value)
                : $this->formatArray($value);
        }

        return '';
    }

    /**
     * @param  array<mixed>  $changes
     */
    private function formatChanges(array $changes): string
    {
        $rows = [];

        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }

            $field = $this->safeScalarText((string) ($change['field'] ?? ''));

            if ($field === '') {
                continue;
            }

            $before = $this->safeScalarText((string) ($change['before'] ?? ''));
            $after = $this->safeScalarText((string) ($change['after'] ?? ''));
            $rows[] = "{$field}: {$before} -> {$after}";
        }

        return implode('; ', $rows);
    }

    /**
     * @param  array<mixed>  $value
     */
    private function formatArray(array $value): string
    {
        $parts = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $nested = $this->formatArray($item);
            } elseif (is_bool($item)) {
                $nested = $item ? 'SI' : 'NO';
            } elseif (is_scalar($item) || $item === null) {
                $nested = $this->safeScalarText((string) $item);
            } else {
                $nested = '';
            }

            if ($nested === '') {
                continue;
            }

            $parts[] = is_string($key)
                ? $this->safeScalarText($key).'='.$nested
                : $nested;
        }

        return implode(', ', $parts);
    }

    private function safeScalarText(string $value): string
    {
        return SensitiveValueSanitizer::sanitizeFreeText($value);
    }
}
