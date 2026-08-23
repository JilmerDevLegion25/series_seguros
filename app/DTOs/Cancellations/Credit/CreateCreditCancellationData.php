<?php

namespace App\DTOs\Cancellations\Credit;

use App\Enums\CancellationOrigin;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use InvalidArgumentException;

final readonly class CreateCreditCancellationData
{
    public const PAYLOAD_SCHEMA = 'credit_create_v1';

    public function __construct(
        public string $holderName,
        public string $holderCedula,
        public string $creditNumber,
        public string $holderPhone,
        public string $holderEmail,
        public MotoCancellationReason $cancellationReason,
        public bool $cancelPersonalAccidents,
        public bool $cancelUnemploymentInsurance,
        public MotoInformationSource $cancellationInformationSource,
        public bool $creditHolderDeclarationAccepted,
        public bool $dataProcessingAccepted,
    ) {
        if (! $this->cancelPersonalAccidents && ! $this->cancelUnemploymentInsurance) {
            throw new InvalidArgumentException('At least one credit insurance must be selected.');
        }
    }

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
            'credit_number' => $this->creditNumber,
            'holder_phone' => $this->holderPhone,
            'holder_email' => $this->holderEmail,
            'cancellation_reason' => $this->cancellationReason->value,
            'cancel_personal_accidents' => $this->cancelPersonalAccidents,
            'cancel_unemployment_insurance' => $this->cancelUnemploymentInsurance,
            'cancellation_information_source' => $this->cancellationInformationSource->value,
            'credit_holder_declaration_accepted' => $this->creditHolderDeclarationAccepted,
            'data_processing_accepted' => $this->dataProcessingAccepted,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        if (($payload['payload_schema'] ?? null) !== self::PAYLOAD_SCHEMA) {
            throw new InvalidArgumentException('Invalid Credit payload schema.');
        }

        return new self(
            holderName: self::stringValue($payload, 'holder_name'),
            holderCedula: self::stringValue($payload, 'holder_cedula'),
            creditNumber: self::stringValue($payload, 'credit_number'),
            holderPhone: self::stringValue($payload, 'holder_phone'),
            holderEmail: self::stringValue($payload, 'holder_email'),
            cancellationReason: MotoCancellationReason::from(self::stringValue($payload, 'cancellation_reason')),
            cancelPersonalAccidents: self::boolValue($payload, 'cancel_personal_accidents'),
            cancelUnemploymentInsurance: self::boolValue($payload, 'cancel_unemployment_insurance'),
            cancellationInformationSource: MotoInformationSource::from(self::stringValue($payload, 'cancellation_information_source')),
            creditHolderDeclarationAccepted: self::boolValue($payload, 'credit_holder_declaration_accepted'),
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
            throw new InvalidArgumentException('Invalid Credit creator.');
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
            throw new InvalidArgumentException("Invalid Credit payload field {$key}.");
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
            throw new InvalidArgumentException("Invalid Credit payload field {$key}.");
        }

        return $value;
    }
}
