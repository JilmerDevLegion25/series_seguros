<?php

namespace App\Http\Requests\Credit;

use App\DTOs\Cancellations\Credit\CreateCreditCancellationData;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Services\Normalization\CreditNumberNormalizer;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class StoreCreditCancellationRequest extends FormRequest
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
            'holder_name' => ['required', 'string', 'max:150'],
            'holder_cedula' => ['required', 'string', 'max:32'],
            'credit_number' => ['required', 'string', 'max:64'],
            'holder_phone' => ['required', 'string', 'max:32'],
            'holder_email' => ['required', 'email', 'max:255'],
            'cancellation_reason' => ['required', Rule::enum(MotoCancellationReason::class)],
            'cancel_personal_accidents' => ['nullable', 'boolean'],
            'cancel_unemployment_insurance' => ['nullable', 'boolean'],
            'cancellation_information_source' => ['required', Rule::enum(MotoInformationSource::class)],
            'credit_holder_declaration_accepted' => ['accepted'],
            'data_processing_accepted' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('cancel_personal_accidents') && ! $this->boolean('cancel_unemployment_insurance')) {
                $validator->errors()->add('cancel_personal_accidents', 'Debe seleccionar al menos un seguro a cancelar.');
            }
        });
    }

    public function toData(
        IdentityNormalizer $identityNormalizer,
        PhoneNormalizer $phoneNormalizer,
        CreditNumberNormalizer $creditNumberNormalizer,
    ): CreateCreditCancellationData {
        return new CreateCreditCancellationData(
            holderName: $this->trimmed('holder_name'),
            holderCedula: $this->normalize(
                'holder_cedula',
                fn (): string => $identityNormalizer->normalize((string) $this->string('holder_cedula')),
            ),
            creditNumber: $this->normalize(
                'credit_number',
                fn (): string => $creditNumberNormalizer->normalize((string) $this->string('credit_number')),
            ),
            holderPhone: $this->normalize(
                'holder_phone',
                fn (): string => $phoneNormalizer->normalize((string) $this->string('holder_phone')),
            ),
            holderEmail: strtolower($this->trimmed('holder_email')),
            cancellationReason: MotoCancellationReason::from((string) $this->validated('cancellation_reason')),
            cancelPersonalAccidents: $this->boolean('cancel_personal_accidents'),
            cancelUnemploymentInsurance: $this->boolean('cancel_unemployment_insurance'),
            cancellationInformationSource: MotoInformationSource::from((string) $this->validated('cancellation_information_source')),
            creditHolderDeclarationAccepted: $this->boolean('credit_holder_declaration_accepted'),
            dataProcessingAccepted: $this->boolean('data_processing_accepted'),
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
