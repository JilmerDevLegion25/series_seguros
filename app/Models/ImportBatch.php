<?php

namespace App\Models;

use App\Enums\CancellationType;
use App\Enums\ImportBatchStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $uploaded_by_user_id
 * @property CancellationType $cancellation_type
 * @property ImportBatchStatus $status
 * @property string $stored_path
 * @property int $total_rows
 * @property int $successful_rows
 * @property int $rejected_rows
 * @property string|null $failure_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class ImportBatch extends Model
{
    protected $fillable = [
        'uploaded_by_user_id',
        'cancellation_type',
        'status',
        'stored_path',
        'total_rows',
        'successful_rows',
        'rejected_rows',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'cancellation_type' => CancellationType::class,
            'status' => ImportBatchStatus::class,
            'total_rows' => 'int',
            'successful_rows' => 'int',
            'rejected_rows' => 'int',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return HasMany<ImportRowResult, $this>
     */
    public function rowResults(): HasMany
    {
        return $this->hasMany(ImportRowResult::class);
    }
}
