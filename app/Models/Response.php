<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $moto_cancellation_id
 * @property int|null $credit_cancellation_id
 * @property CarbonImmutable $cancellation_date
 * @property string $observation
 * @property int $created_by_user_id
 * @property CarbonImmutable|null $created_at
 */
final class Response extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'moto_cancellation_id',
        'credit_cancellation_id',
        'cancellation_date',
        'observation',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'cancellation_date' => 'immutable_date',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
