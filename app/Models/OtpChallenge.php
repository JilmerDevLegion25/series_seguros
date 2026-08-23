<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_reference
 * @property OtpPurpose $purpose
 * @property int|null $target_user_id
 * @property string $destination_snapshot
 * @property string|null $encrypted_payload
 * @property string $otp_mac
 * @property int $failed_attempts
 * @property int $emission_count
 * @property CarbonImmutable|null $last_emitted_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $invalidated_at
 * @property string|null $invalidation_reason
 * @property User|null $targetUser
 */
final class OtpChallenge extends Model
{
    protected $fillable = [
        'public_reference',
        'purpose',
        'target_user_id',
        'destination_snapshot',
        'encrypted_payload',
        'otp_mac',
        'failed_attempts',
        'emission_count',
        'last_emitted_at',
        'expires_at',
        'consumed_at',
        'invalidated_at',
        'invalidation_reason',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'target_user_id' => 'int',
            'failed_attempts' => 'int',
            'emission_count' => 'int',
            'last_emitted_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function isExpired(?CarbonImmutable $now = null): bool
    {
        return ($now ?? CarbonImmutable::now())->greaterThanOrEqualTo($this->expires_at);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isInvalidated(): bool
    {
        return $this->invalidated_at !== null;
    }

    public function isTerminal(): bool
    {
        return $this->isConsumed() || $this->isInvalidated();
    }
}
