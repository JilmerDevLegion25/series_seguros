<?php

namespace App\Support\Changes;

final readonly class ChangeSet
{
    /**
     * @param  list<FieldChange>  $changes
     */
    public function __construct(
        public array $changes,
    ) {}

    /**
     * @param  array<string, bool|int|string|null>  $before
     * @param  array<string, bool|int|string|null>  $after
     */
    public static function fromArrays(array $before, array $after): self
    {
        $changes = [];

        foreach ($after as $field => $afterValue) {
            $beforeValue = $before[$field] ?? null;

            if ($beforeValue !== $afterValue) {
                $changes[] = new FieldChange($field, $beforeValue, $afterValue);
            }
        }

        return new self($changes);
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return array_map(
            static fn (FieldChange $change): string => $change->field,
            $this->changes,
        );
    }

    /**
     * @return list<array{field: string, before: bool|int|string|null, after: bool|int|string|null}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (FieldChange $change): array => $change->toArray(masked: false),
            $this->changes,
        );
    }

    /**
     * @return list<array{field: string, before: bool|int|string|null, after: bool|int|string|null}>
     */
    public function toAuditArray(): array
    {
        return array_map(
            static fn (FieldChange $change): array => $change->toArray(masked: true),
            $this->changes,
        );
    }
}
