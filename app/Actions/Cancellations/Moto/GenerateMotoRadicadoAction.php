<?php

namespace App\Actions\Cancellations\Moto;

use App\Actions\Cancellations\Concerns\AuthorizesCancellationMutations;
use App\Actions\Sms\DeliverSmsAttemptAction;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Enums\SmsAttemptStatus;
use App\Enums\SmsPurpose;
use App\Exceptions\CancellationMutationException;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\MotoCancellation;
use App\Models\SmsAttempt;
use App\Models\User;
use App\Services\Radicado\RadicadoAllocator;
use App\Services\Sms\RadicadoSmsMessageFactory;
use Illuminate\Support\Facades\DB;

final readonly class GenerateMotoRadicadoAction
{
    use AuthorizesCancellationMutations;

    public function __construct(
        private RadicadoAllocator $radicadoAllocator,
        private DeliverSmsAttemptAction $deliverSmsAttempt,
        private RadicadoSmsMessageFactory $messageFactory,
    ) {}

    /**
     * @throws CancellationMutationException
     */
    public function execute(MotoCancellation $moto, User $actor, ?string $requestId = null): MotoCancellation
    {
        $this->assertAdvisorCan($actor, PermissionKey::CANCELLATIONS_UPDATE);

        [$radicatedMoto, $smsAttempt] = DB::transaction(function () use ($moto, $actor, $requestId): array {
            /** @var MotoCancellation $lockedMoto */
            $lockedMoto = MotoCancellation::query()
                ->whereKey($moto->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedMoto->property_lien_adeinco) {
                throw CancellationMutationException::invalidData('MOTO_LIEN_REQUIRED');
            }

            if ($lockedMoto->radicado !== null) {
                throw CancellationMutationException::invalidData('RADICADO_ALREADY_EXISTS');
            }

            if ($lockedMoto->status !== CancellationStatus::PENDIENTE_RADICACION) {
                throw CancellationMutationException::invalidData('PENDING_RADICACION_REQUIRED');
            }

            $radicado = $this->radicadoAllocator->allocate(CancellationType::MOTO);
            $nextVersion = $lockedMoto->version + 1;

            $lockedMoto->forceFill([
                'radicado' => $radicado,
                'status' => CancellationStatus::EN_GESTION,
                'version' => $nextVersion,
            ])->save();

            $metadata = [
                'event' => AuditEventType::RADICADO_GENERATED->value,
                'type' => CancellationType::MOTO->value,
                'radicado' => $radicado,
                'version' => $nextVersion,
            ];

            Activity::query()->create([
                'moto_cancellation_id' => $lockedMoto->id,
                'actor_user_id' => $actor->id,
                'type' => ActivityType::RADICADO_GENERATED,
                'metadata' => $metadata,
            ]);

            Audit::query()->create([
                'moto_cancellation_id' => $lockedMoto->id,
                'actor_user_id' => $actor->id,
                'event_type' => AuditEventType::RADICADO_GENERATED,
                'request_id' => $requestId,
                'metadata' => $metadata,
            ]);

            $smsAttempt = SmsAttempt::query()->create([
                'moto_cancellation_id' => $lockedMoto->id,
                'purpose' => SmsPurpose::RADICADO,
                'status' => SmsAttemptStatus::PENDING,
                'destination' => $lockedMoto->holder_phone,
            ]);

            return [$lockedMoto, $smsAttempt];
        });

        $this->deliverSmsAttempt->execute(
            $smsAttempt,
            $this->messageFactory->makeForMoto($radicatedMoto),
        );

        return $radicatedMoto->refresh();
    }
}
