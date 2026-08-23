<?php

namespace App\Support\Changes;

use App\Support\Security\SensitiveValueSanitizer;

final readonly class FieldChange
{
    public function __construct(
        public string $field,
        public bool|int|string|null $before,
        public bool|int|string|null $after,
    ) {}

    /**
     * @return array{field: string, before: bool|int|string|null, after: bool|int|string|null}
     */
    public function toArray(bool $masked = false): array
    {
        return [
            'field' => $this->field,
            'before' => $masked ? $this->masked($this->before) : $this->before,
            'after' => $masked ? $this->masked($this->after) : $this->after,
        ];
    }

    private function masked(bool|int|string|null $value): bool|int|string|null
    {
        if (! is_string($value)) {
            return $value;
        }

        if (in_array($this->field, ['holder_cedula', 'holder_phone', 'holder_email', 'holder_name', 'credit_owner_name', 'credit_owner_cedula'], true)) {
            return $this->maskString($value);
        }

        return $value;
    }

    private function maskString(string $value): string
    {
        return SensitiveValueSanitizer::maskPreservingLastTwo($value);
    }
}
