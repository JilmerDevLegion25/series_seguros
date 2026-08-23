<?php

namespace App\Actions\Cancellations\Moto;

use App\Actions\Cancellations\Concerns\AuthorizesCancellationMutations;
use App\DTOs\Cancellations\CancellationMutationResult;
use App\DTOs\Cancellations\ReassignCancellationAdvisorData;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Exceptions\CancellationMutationException;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\MotoCancellation;
use App\Models\Role;
use App\Models\User;
use App\Support\Changes\ChangeSet;
use App\Support\Security\SensitiveValueSanitizer;
use Illuminate\Support\Facades\DB;

final class ReassignMotoCancellationAction
{
    use AuthorizesCancellationMutations;

    public function execute(
        MotoCancellation $moto,
        ReassignCancellationAdvisorData $data,
        User $actor,
        ?string $requestId = null,
    ): CancellationMutationResult {
        $this->assertAdvisorCan($actor, PermissionKey::CANCELLATIONS_REASSIGN);

        return DB::transaction(function () use ($moto, $data, $actor, $requestId): CancellationMutationResult {
            /** @var MotoCancellation $lockedMoto */
            $lockedMoto = MotoCancellation::query()
                ->whereKey($moto->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMutable($lockedMoto, $data->expectedVersion);
            $this->assertAssignedAdvisorExists($data->assignedAdvisorUserId);

            $after = ['assigned_advisor_user_id' => $data->assignedAdvisorUserId];
            $changeSet = ChangeSet::fromArrays($this->snapshot($lockedMoto), $after);

            if ($changeSet->isEmpty()) {
                return new CancellationMutationResult($lockedMoto, false, $changeSet);
            }

            $lockedMoto->forceFill(array_merge($after, [
                'version' => $lockedMoto->version + 1,
            ]))->save();

            $this->recordReassignment($lockedMoto, $actor, $data->reason, $changeSet, $requestId);

            return new CancellationMutationResult($lockedMoto, true, $changeSet);
        });
    }

    private function assertMutable(MotoCancellation $moto, int $expectedVersion): void
    {
        if ($moto->status !== CancellationStatus::EN_GESTION) {
            throw CancellationMutationException::terminal();
        }

        if ($moto->version !== $expectedVersion) {
            throw CancellationMutationException::staleVersion();
        }
    }

    private function assertAssignedAdvisorExists(int $assignedAdvisorUserId): void
    {
        $exists = User::query()
            ->whereKey($assignedAdvisorUserId)
            ->where('role_id', Role::idFor(RoleCode::ADVISOR))
            ->where('status', UserStatus::ACTIVE->value)
            ->exists();

        if (! $exists) {
            throw CancellationMutationException::invalidData('ASSIGNED_ADVISOR_NOT_FOUND');
        }
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function snapshot(MotoCancellation $moto): array
    {
        return [
            'assigned_advisor_user_id' => $moto->assigned_advisor_user_id,
        ];
    }

    private function recordReassignment(
        MotoCancellation $moto,
        User $actor,
        string $reason,
        ChangeSet $changeSet,
        ?string $requestId,
    ): void {
        Activity::query()->create([
            'moto_cancellation_id' => $moto->id,
            'actor_user_id' => $actor->id,
            'type' => ActivityType::OWNER_REASSIGNED,
            'metadata' => [
                'event' => AuditEventType::OWNER_REASSIGNED->value,
                'type' => CancellationType::MOTO->value,
                'radicado' => $moto->radicado,
                'version' => $moto->version,
                'changed_fields' => $changeSet->fields(),
            ],
        ]);

        Audit::query()->create([
            'moto_cancellation_id' => $moto->id,
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::OWNER_REASSIGNED,
            'request_id' => $requestId,
            'metadata' => [
                'type' => CancellationType::MOTO->value,
                'radicado' => $moto->radicado,
                'reason' => SensitiveValueSanitizer::sanitizeFreeText($reason),
                'version' => $moto->version,
                'changed_fields' => $changeSet->fields(),
                'changes' => $changeSet->toAuditArray(),
            ],
        ]);
    }
}
