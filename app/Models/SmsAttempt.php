<?php

namespace App\Models;

use App\Enums\SmsAttemptStatus;
use App\Enums\SmsPurpose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $moto_cancellation_id
 * @property int|null $credit_cancellation_id
 * @property SmsPurpose $purpose
 * @property SmsAttemptStatus $status
 * @property bool $is_manual_retry
 * @property string $destination
 * @property string|null $provider_reference
 * @property string|null $safe_error_code
 * @property string|null $safe_error_message
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $completed_at
 */
final class SmsAttempt extends Model
{
    protected $fillable = [
        'moto_cancellation_id',
        'credit_cancellation_id',
        'purpose',
        'status',
        'is_manual_retry',
        'destination',
        'provider_reference',
        'safe_error_code',
        'safe_error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => SmsPurpose::class,
            'status' => SmsAttemptStatus::class,
            'is_manual_retry' => 'bool',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<MotoCancellation, $this>
     */
    public function motoCancellation(): BelongsTo
    {
        return $this->belongsTo(MotoCancellation::class);
    }

    /**
     * @return BelongsTo<CreditCancellation, $this>
     */
    public function creditCancellation(): BelongsTo
    {
        return $this->belongsTo(CreditCancellation::class);
    }
}
