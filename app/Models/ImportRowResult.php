<?php

namespace App\Models;

use App\Enums\ImportRowReason;
use App\Enums\ImportRowStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $import_batch_id
 * @property int $row_number
 * @property int|null $radicado
 * @property ImportRowStatus $status
 * @property ImportRowReason|null $reason
 * @property string $message
 * @property int|null $moto_cancellation_id
 * @property int|null $credit_cancellation_id
 * @property int|null $response_id
 * @property CarbonImmutable|null $created_at
 */
final class ImportRowResult extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'import_batch_id',
        'row_number',
        'radicado',
        'status',
        'reason',
        'message',
        'moto_cancellation_id',
        'credit_cancellation_id',
        'response_id',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'int',
            'radicado' => 'int',
            'status' => ImportRowStatus::class,
            'reason' => ImportRowReason::class,
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
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
     * @return BelongsTo<Response, $this>
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }
}
