<?php

namespace App\Models;

use App\Enums\AuditEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $moto_cancellation_id
 * @property int|null $credit_cancellation_id
 * @property int|null $actor_user_id
 * @property AuditEventType $event_type
 * @property string|null $request_id
 * @property array<string, mixed>|null $metadata
 */
final class Audit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'moto_cancellation_id',
        'credit_cancellation_id',
        'actor_user_id',
        'event_type',
        'request_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AuditEventType::class,
            'metadata' => 'array',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
