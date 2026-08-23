<?php

namespace App\Models;

use App\Enums\CancellationOrigin;
use App\Enums\CancellationStatus;
use App\Enums\MotoCancellationReason;
use App\Enums\MotoInformationSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $otp_challenge_id
 * @property int $radicado
 * @property int $owner_user_id
 * @property int $created_by_user_id
 * @property int|null $assigned_advisor_user_id
 * @property CancellationOrigin $origin
 * @property CancellationStatus $status
 * @property int $version
 * @property string $holder_name
 * @property string $holder_cedula
 * @property bool $property_lien_adeinco
 * @property string $plate
 * @property string $holder_phone
 * @property string $holder_email
 * @property MotoCancellationReason $cancellation_reason
 * @property MotoInformationSource $cancellation_information_source
 * @property bool $is_credit_holder
 * @property string|null $credit_owner_name
 * @property string|null $credit_owner_cedula
 * @property bool $ownership_declaration_accepted
 * @property bool $data_processing_accepted
 * @property User|null $owner
 * @property User|null $creator
 */
final class MotoCancellation extends Model
{
    protected $fillable = [
        'otp_challenge_id',
        'radicado',
        'owner_user_id',
        'created_by_user_id',
        'assigned_advisor_user_id',
        'origin',
        'status',
        'version',
        'holder_name',
        'holder_cedula',
        'property_lien_adeinco',
        'plate',
        'holder_phone',
        'holder_email',
        'cancellation_reason',
        'cancellation_information_source',
        'is_credit_holder',
        'credit_owner_name',
        'credit_owner_cedula',
        'ownership_declaration_accepted',
        'data_processing_accepted',
    ];

    protected function casts(): array
    {
        return [
            'radicado' => 'int',
            'owner_user_id' => 'int',
            'created_by_user_id' => 'int',
            'assigned_advisor_user_id' => 'int',
            'origin' => CancellationOrigin::class,
            'status' => CancellationStatus::class,
            'version' => 'int',
            'property_lien_adeinco' => 'bool',
            'cancellation_reason' => MotoCancellationReason::class,
            'cancellation_information_source' => MotoInformationSource::class,
            'is_credit_holder' => 'bool',
            'ownership_declaration_accepted' => 'bool',
            'data_processing_accepted' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedAdvisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_advisor_user_id');
    }

    /**
     * @return BelongsTo<OtpChallenge, $this>
     */
    public function otpChallenge(): BelongsTo
    {
        return $this->belongsTo(OtpChallenge::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @return HasMany<Audit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    /**
     * @return HasMany<SmsAttempt, $this>
     */
    public function smsAttempts(): HasMany
    {
        return $this->hasMany(SmsAttempt::class);
    }

    /**
     * @return HasOne<Response, $this>
     */
    public function response(): HasOne
    {
        return $this->hasOne(Response::class);
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
