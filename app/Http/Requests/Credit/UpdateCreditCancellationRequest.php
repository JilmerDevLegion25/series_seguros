<?php

namespace App\Http\Requests\Credit;

use App\DTOs\Cancellations\Credit\UpdateCreditCancellationData;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Services\Normalization\CreditNumberNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class UpdateCreditCancellationRequest extends FormRequest
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
            'credit_number' => ['sometimes', 'required', 'string', 'max:64'],
            'cancellation_reason' => ['sometimes', 'required', Rule::enum(MotoCancellationReason::class)],
            'cancel_personal_accidents' => ['sometimes', 'boolean'],
            'cancel_unemployment_insurance' => ['sometimes', 'boolean'],
            'cancellation_information_source' => ['sometimes', 'required', Rule::enum(MotoInformationSource::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                ($this->has('cancel_personal_accidents') || $this->has('cancel_unemployment_insurance'))
                && ! $this->boolean('cancel_personal_accidents')
                && ! $this->boolean('cancel_unemployment_insurance')
            ) {
                $validator->errors()->add('cancel_personal_accidents', 'Debe seleccionar al menos un seguro a cancelar.');
            }
        });
    }

    public function toData(
        PhoneNormalizer $phoneNormalizer,
        CreditNumberNormalizer $creditNumberNormalizer,
    ): UpdateCreditCancellationData {
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

        if (array_key_exists('credit_number', $validated)) {
            $fields['credit_number'] = $this->normalize(
                'credit_number',
                fn (): string => $creditNumberNormalizer->normalize((string) $this->string('credit_number')),
            );
        }

        if (array_key_exists('cancellation_reason', $validated)) {
            $fields['cancellation_reason'] = MotoCancellationReason::from((string) $validated['cancellation_reason'])->value;
        }

        if (array_key_exists('cancel_personal_accidents', $validated)) {
            $fields['cancel_personal_accidents'] = $this->boolean('cancel_personal_accidents');
        }

        if (array_key_exists('cancel_unemployment_insurance', $validated)) {
            $fields['cancel_unemployment_insurance'] = $this->boolean('cancel_unemployment_insurance');
        }

        if (array_key_exists('cancellation_information_source', $validated)) {
            $fields['cancellation_information_source'] = MotoInformationSource::from((string) $validated['cancellation_information_source'])->value;
        }

        return new UpdateCreditCancellationData(
            expectedVersion: (int) $this->integer('expected_version'),
            reason: $this->trimmed('reason'),
            fields: $fields,
        );
    }

    private function trimmed(string $field): string
    {
        return trim((string) $this->string($field));
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
