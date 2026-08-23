<?php

namespace App\Http\Requests\Moto;

use App\DTOs\Cancellations\Moto\UpdateMotoCancellationData;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Normalization\PlateNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class UpdateMotoCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'holder_name' => ['sometimes', 'required', 'string', 'max:150'],
            'holder_phone' => ['sometimes', 'required', 'string', 'max:32'],
            'holder_email' => ['sometimes', 'required', 'email', 'max:255'],
            'property_lien_adeinco' => ['sometimes', 'boolean'],
            'plate' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z0-9]{6}$/'],
            'cancellation_reason' => ['sometimes', 'required', Rule::enum(MotoCancellationReason::class)],
            'cancellation_information_source' => ['sometimes', 'required', Rule::enum(MotoInformationSource::class)],
            'is_credit_holder' => ['sometimes', 'boolean'],
            'credit_owner_name' => ['nullable', 'required_if:is_credit_holder,0', 'string', 'max:150'],
            'credit_owner_cedula' => ['nullable', 'required_if:is_credit_holder,0', 'string', 'max:32'],
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

    public function toData(
        PhoneNormalizer $phoneNormalizer,
        PlateNormalizer $plateNormalizer,
        IdentityNormalizer $identityNormalizer,
    ): UpdateMotoCancellationData {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $fields = [];

        if (array_key_exists('holder_name', $validated)) {
            $fields['holder_name'] = $this->trimmed('holder_name');
        }

        if (array_key_exists('holder_phone', $validated)) {
            $fields['holder_phone'] = $this->normalize(
                'holder_phone',
                fn (): string => $phoneNormalizer->normalize((string) $this->string('holder_phone')),
            );
        }

        if (array_key_exists('holder_email', $validated)) {
            $fields['holder_email'] = strtolower($this->trimmed('holder_email'));
        }

        if (array_key_exists('property_lien_adeinco', $validated)) {
            $fields['property_lien_adeinco'] = $this->boolean('property_lien_adeinco');
        }

        if (array_key_exists('plate', $validated)) {
            $fields['plate'] = $this->normalize(
                'plate',
                fn (): string => $plateNormalizer->normalize((string) $this->string('plate')),
            );
        }

        if (array_key_exists('cancellation_reason', $validated)) {
            $fields['cancellation_reason'] = MotoCancellationReason::from((string) $validated['cancellation_reason'])->value;
        }

        if (array_key_exists('cancellation_information_source', $validated)) {
            $fields['cancellation_information_source'] = MotoInformationSource::from((string) $validated['cancellation_information_source'])->value;
        }

        if (array_key_exists('is_credit_holder', $validated)) {
            $fields['is_credit_holder'] = $this->boolean('is_credit_holder');
        }

        if (array_key_exists('credit_owner_name', $validated)) {
            $fields['credit_owner_name'] = $this->nullableTrimmed('credit_owner_name');
        }

        if (array_key_exists('credit_owner_cedula', $validated)) {
            $fields['credit_owner_cedula'] = $this->nullableTrimmed('credit_owner_cedula') === null
                ? null
                : $this->normalize(
                    'credit_owner_cedula',
                    fn (): string => $identityNormalizer->normalize((string) $this->string('credit_owner_cedula')),
                );
        }

        return new UpdateMotoCancellationData(
            expectedVersion: (int) $this->integer('expected_version'),
            reason: $this->trimmed('reason'),
            fields: $fields,
        );
    }

    private function trimmed(string $field): string
    {
        return trim((string) $this->string($field));
    }

    private function nullableTrimmed(string $field): ?string
    {
        $value = $this->trimmed($field);

        return $value === '' ? null : $value;
    }

    /**
     * @param  callable(): string  $callback
     */
    private function normalize(string $field, callable $callback): string
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
