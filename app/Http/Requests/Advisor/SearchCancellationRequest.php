<?php

namespace App\Http\Requests\Advisor;

use App\DTOs\Cancellations\CancellationSearchFilters;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Models\User;
use App\Services\Normalization\CreditNumberNormalizer;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Normalization\PlateNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SearchCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->isAdvisor()
            && $user->can(PermissionKey::CANCELLATIONS_VIEW->value);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in(['ALL', CancellationType::MOTO->value, CancellationType::CREDIT->value])],
            'status' => ['nullable', 'string', Rule::in(array_map(static fn (CancellationStatus $status): string => $status->value, CancellationStatus::cases()))],
            'radicado' => ['nullable', 'integer', 'min:1'],
            'holder_name' => ['nullable', 'string', 'max:150'],
            'holder_cedula' => ['nullable', 'string', 'max:32'],
            'holder_email' => ['nullable', 'email', 'max:255'],
            'holder_phone' => ['nullable', 'string', 'max:32'],
            'plate' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{6}$/'],
            'credit_number' => ['nullable', 'string', 'max:50'],
            'assigned_advisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', 'string', Rule::in(array_keys(self::sortOptions()))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plate.regex' => 'La placa debe tener exactamente 6 letras o numeros, sin signos ni guiones.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->rawStringValue('type') ?? 'ALL';
            $plate = $this->rawStringValue('plate');
            $creditNumber = $this->rawStringValue('credit_number');
            $createdFrom = $this->rawStringValue('created_from');
            $createdTo = $this->rawStringValue('created_to');

            if ($type === CancellationType::CREDIT->value && $plate !== null) {
                $validator->errors()->add('plate', 'La placa solo aplica para Moto.');
            }

            if ($type === CancellationType::MOTO->value && $creditNumber !== null) {
                $validator->errors()->add('credit_number', 'El numero de credito solo aplica para Credit.');
            }

            if ($plate !== null && $creditNumber !== null) {
                $validator->errors()->add('credit_number', 'No combines placa y numero de credito.');
            }

            if ($createdFrom !== null && $createdTo !== null && $createdTo < $createdFrom) {
                $validator->errors()->add('created_to', 'La fecha final debe ser posterior o igual a la inicial.');
            }
        });
    }

    public function toFilters(
        IdentityNormalizer $identityNormalizer,
        PhoneNormalizer $phoneNormalizer,
        PlateNormalizer $plateNormalizer,
        CreditNumberNormalizer $creditNumberNormalizer,
    ): CancellationSearchFilters {
        return new CancellationSearchFilters(
            type: $this->typeFilter(),
            status: $this->statusFilter(),
            radicado: $this->integerFilter('radicado'),
            holderName: $this->stringValue('holder_name'),
            holderCedula: $this->normalized('holder_cedula', fn (string $value): string => $identityNormalizer->normalize($value)),
            holderEmail: $this->normalizedEmail(),
            holderPhone: $this->normalized('holder_phone', fn (string $value): string => $phoneNormalizer->normalize($value)),
            plate: $this->normalized('plate', fn (string $value): string => $plateNormalizer->normalize($value)),
            creditNumber: $this->normalized('credit_number', fn (string $value): string => $creditNumberNormalizer->normalize($value)),
            assignedAdvisorUserId: $this->integerFilter('assigned_advisor_user_id'),
            createdFrom: $this->dateFilter('created_from', endOfDay: false),
            createdTo: $this->dateFilter('created_to', endOfDay: true),
            sort: $this->stringValue('sort') ?? 'created_at_desc',
        );
    }

    public function perPage(): int
    {
        return min(max((int) ($this->validated('per_page') ?? 10), 1), 50);
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'ALL' => 'Todos',
            CancellationType::MOTO->value => 'Moto',
            CancellationType::CREDIT->value => 'Credit',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            CancellationStatus::EN_GESTION->value => 'En gestion',
            CancellationStatus::RESPUESTA_OBTENIDA->value => 'Respuesta obtenida',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sortOptions(): array
    {
        return [
            'created_at_desc' => 'Mas recientes',
            'created_at_asc' => 'Mas antiguas',
            'cancellation_type_asc' => 'Tipo ascendente',
            'cancellation_type_desc' => 'Tipo descendente',
            'radicado_asc' => 'Radicado ascendente',
            'radicado_desc' => 'Radicado descendente',
            'holder_name_asc' => 'Titular ascendente',
            'holder_name_desc' => 'Titular descendente',
            'holder_email_asc' => 'Contacto ascendente',
            'holder_email_desc' => 'Contacto descendente',
            'plate_asc' => 'Placa ascendente',
            'plate_desc' => 'Placa descendente',
            'credit_number_asc' => 'Credito ascendente',
            'credit_number_desc' => 'Credito descendente',
            'status_asc' => 'Estado ascendente',
            'status_desc' => 'Estado descendente',
        ];
    }

    private function typeFilter(): ?CancellationType
    {
        $value = $this->stringValue('type');

        if ($value === null || $value === 'ALL') {
            return null;
        }

        return CancellationType::from($value);
    }

    private function statusFilter(): ?CancellationStatus
    {
        $value = $this->stringValue('status');

        return $value === null ? null : CancellationStatus::from($value);
    }

    private function integerFilter(string $key): ?int
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function stringValue(string $key): ?string
    {
        $value = $this->validated($key);

        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function rawStringValue(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  callable(string): string  $normalizer
     */
    private function normalized(string $key, callable $normalizer): ?string
    {
        $value = $this->stringValue($key);

        if ($value === null) {
            return null;
        }

        try {
            return $normalizer($value);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                $key => $exception->getMessage(),
            ]);
        }
    }

    private function normalizedEmail(): ?string
    {
        $value = $this->stringValue('holder_email');

        return $value === null ? null : strtolower($value);
    }

    private function dateFilter(string $key, bool $endOfDay): ?CarbonImmutable
    {
        $value = $this->stringValue($key);

        if ($value === null) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

        if (! $date instanceof CarbonImmutable) {
            return null;
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
