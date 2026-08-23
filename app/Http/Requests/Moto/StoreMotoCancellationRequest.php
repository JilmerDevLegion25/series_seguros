<?php

namespace App\Http\Requests\Moto;

use App\DTOs\Cancellations\Moto\CreateMotoCancellationData;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use App\Services\Normalization\IdentityNormalizer;
use App\Services\Normalization\PhoneNormalizer;
use App\Services\Normalization\PlateNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class StoreMotoCancellationRequest extends FormRequest
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
            'property_lien_adeinco' => ['required', 'boolean'],
            'plate' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6}$/'],
            'holder_phone' => ['required', 'string', 'max:32'],
            'holder_email' => ['required', 'email', 'max:255'],
            'cancellation_reason' => ['required', Rule::enum(MotoCancellationReason::class)],
            'cancellation_information_source' => ['required', Rule::enum(MotoInformationSource::class)],
            'is_credit_holder' => ['required', 'boolean'],
            'credit_owner_name' => ['nullable', 'required_if:is_credit_holder,0', 'string', 'max:150'],
            'credit_owner_cedula' => ['nullable', 'required_if:is_credit_holder,0', 'string', 'max:32'],
            'ownership_declaration_accepted' => ['accepted'],
            'data_processing_accepted' => ['accepted'],
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
        IdentityNormalizer $identityNormalizer,
        PhoneNormalizer $phoneNormalizer,
        PlateNormalizer $plateNormalizer,
    ): CreateMotoCancellationData {
        $isCreditHolder = $this->boolean('is_credit_holder');
        $creditOwnerName = $this->trimmed('credit_owner_name');
        $creditOwnerCedula = $this->normalize(
            'credit_owner_cedula',
            fn (): string => $identityNormalizer->normalize((string) $this->string('credit_owner_cedula')),
        );

        return new CreateMotoCancellationData(
            holderName: $this->trimmed('holder_name'),
            holderCedula: $this->normalize(
                'holder_cedula',
                fn (): string => $identityNormalizer->normalize((string) $this->string('holder_cedula')),
            ),
            propertyLienAdeinco: $this->boolean('property_lien_adeinco'),
            plate: $this->normalize(
                'plate',
                fn (): string => $plateNormalizer->normalize((string) $this->string('plate')),
            ),
            holderPhone: $this->normalize(
                'holder_phone',
                fn (): string => $phoneNormalizer->normalize((string) $this->string('holder_phone')),
            ),
            holderEmail: strtolower($this->trimmed('holder_email')),
            cancellationReason: MotoCancellationReason::from((string) $this->validated('cancellation_reason')),
            cancellationInformationSource: MotoInformationSource::from((string) $this->validated('cancellation_information_source')),
            isCreditHolder: $isCreditHolder,
            creditOwnerName: $creditOwnerName,
            creditOwnerCedula: $creditOwnerCedula,
            ownershipDeclarationAccepted: $this->boolean('ownership_declaration_accepted'),
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
