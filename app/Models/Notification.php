<?php

namespace App\Models;

use App\Enums\NotificationType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property NotificationType $type
 * @property int|null $moto_cancellation_id
 * @property int|null $credit_cancellation_id
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 */
final class Notification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'moto_cancellation_id',
        'credit_cancellation_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'read_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
