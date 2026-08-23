<?php

namespace App\DTOs\Cancellations\Moto;

use App\Enums\CancellationOrigin;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use InvalidArgumentException;

final readonly class CreateMotoCancellationData
{
    public const PAYLOAD_SCHEMA = 'moto_create_v1';

    public function __construct(
        public string $holderName,
        public string $holderCedula,
        public bool $propertyLienAdeinco,
        public string $plate,
        public string $holderPhone,
        public string $holderEmail,
        public MotoCancellationReason $cancellationReason,
        public MotoInformationSource $cancellationInformationSource,
        public bool $isCreditHolder,
        public ?string $creditOwnerName,
        public ?string $creditOwnerCedula,
        public bool $ownershipDeclarationAccepted,
        public bool $dataProcessingAccepted,
    ) {}

    /**
     * @return array<string, bool|int|string|null>
     */
    public function toPayload(CancellationOrigin $origin, ?int $createdByUserId): array
    {
        return [
            'payload_schema' => self::PAYLOAD_SCHEMA,
            'origin' => $origin->value,
            'created_by_user_id' => $createdByUserId,
            'holder_name' => $this->holderName,
            'holder_cedula' => $this->holderCedula,
            'property_lien_adeinco' => $this->propertyLienAdeinco,
            'plate' => $this->plate,
            'holder_phone' => $this->holderPhone,
            'holder_email' => $this->holderEmail,
            'cancellation_reason' => $this->cancellationReason->value,
            'cancellation_information_source' => $this->cancellationInformationSource->value,
            'is_credit_holder' => $this->isCreditHolder,
            'credit_owner_name' => $this->creditOwnerName,
            'credit_owner_cedula' => $this->creditOwnerCedula,
            'ownership_declaration_accepted' => $this->ownershipDeclarationAccepted,
            'data_processing_accepted' => $this->dataProcessingAccepted,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        if (($payload['payload_schema'] ?? null) !== self::PAYLOAD_SCHEMA) {
            throw new InvalidArgumentException('Invalid Moto payload schema.');
        }

        return new self(
            holderName: self::stringValue($payload, 'holder_name'),
            holderCedula: self::stringValue($payload, 'holder_cedula'),
            propertyLienAdeinco: self::boolValue($payload, 'property_lien_adeinco'),
            plate: self::stringValue($payload, 'plate'),
            holderPhone: self::stringValue($payload, 'holder_phone'),
            holderEmail: self::stringValue($payload, 'holder_email'),
            cancellationReason: MotoCancellationReason::from(self::stringValue($payload, 'cancellation_reason')),
            cancellationInformationSource: MotoInformationSource::from(self::stringValue($payload, 'cancellation_information_source')),
            isCreditHolder: self::boolValue($payload, 'is_credit_holder'),
            creditOwnerName: self::nullableStringValue($payload, 'credit_owner_name'),
            creditOwnerCedula: self::nullableStringValue($payload, 'credit_owner_cedula'),
            ownershipDeclarationAccepted: self::boolValue($payload, 'ownership_declaration_accepted'),
            dataProcessingAccepted: self::boolValue($payload, 'data_processing_accepted'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function originFromPayload(array $payload): CancellationOrigin
    {
        return CancellationOrigin::from(self::stringValue($payload, 'origin'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function createdByUserIdFromPayload(array $payload): ?int
    {
        $value = $payload['created_by_user_id'] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value)) {
            throw new InvalidArgumentException('Invalid Moto creator.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Invalid Moto payload field {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function nullableStringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Invalid Moto payload field {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function boolValue(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? null;

        if (! is_bool($value)) {
            throw new InvalidArgumentException("Invalid Moto payload field {$key}.");
        }

        return $value;
    }
}
