<?php

namespace App\Actions\Cancellations\Credit;

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
use App\Models\CreditCancellation;
use App\Models\Role;
use App\Models\User;
use App\Support\Changes\ChangeSet;
use App\Support\Security\SensitiveValueSanitizer;
use Illuminate\Support\Facades\DB;

final class ReassignCreditCancellationAction
{
    use AuthorizesCancellationMutations;

    public function execute(
        CreditCancellation $credit,
        ReassignCancellationAdvisorData $data,
        User $actor,
        ?string $requestId = null,
    ): CancellationMutationResult {
        $this->assertAdvisorCan($actor, PermissionKey::CANCELLATIONS_REASSIGN);

        return DB::transaction(function () use ($credit, $data, $actor, $requestId): CancellationMutationResult {
            /** @var CreditCancellation $lockedCredit */
            $lockedCredit = CreditCancellation::query()
                ->whereKey($credit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMutable($lockedCredit, $data->expectedVersion);
            $this->assertAssignedAdvisorExists($data->assignedAdvisorUserId);

            $after = ['assigned_advisor_user_id' => $data->assignedAdvisorUserId];
            $changeSet = ChangeSet::fromArrays($this->snapshot($lockedCredit), $after);

            if ($changeSet->isEmpty()) {
                return new CancellationMutationResult($lockedCredit, false, $changeSet);
            }

            $lockedCredit->forceFill(array_merge($after, [
                'version' => $lockedCredit->version + 1,
            ]))->save();

            $this->recordReassignment($lockedCredit, $actor, $data->reason, $changeSet, $requestId);

            return new CancellationMutationResult($lockedCredit, true, $changeSet);
        });
    }

    private function assertMutable(CreditCancellation $credit, int $expectedVersion): void
    {
        if ($credit->status !== CancellationStatus::EN_GESTION) {
            throw CancellationMutationException::terminal();
        }

        if ($credit->version !== $expectedVersion) {
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
    private function snapshot(CreditCancellation $credit): array
    {
        return [
            'assigned_advisor_user_id' => $credit->assigned_advisor_user_id,
        ];
    }

    private function recordReassignment(
        CreditCancellation $credit,
        User $actor,
        string $reason,
        ChangeSet $changeSet,
        ?string $requestId,
    ): void {
        Activity::query()->create([
            'credit_cancellation_id' => $credit->id,
            'actor_user_id' => $actor->id,
            'type' => ActivityType::OWNER_REASSIGNED,
            'metadata' => [
                'event' => AuditEventType::OWNER_REASSIGNED->value,
                'type' => CancellationType::CREDIT->value,
                'radicado' => $credit->radicado,
                'version' => $credit->version,
                'changed_fields' => $changeSet->fields(),
            ],
        ]);

        Audit::query()->create([
            'credit_cancellation_id' => $credit->id,
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::OWNER_REASSIGNED,
            'request_id' => $requestId,
            'metadata' => [
                'type' => CancellationType::CREDIT->value,
                'radicado' => $credit->radicado,
                'reason' => SensitiveValueSanitizer::sanitizeFreeText($reason),
                'version' => $credit->version,
                'changed_fields' => $changeSet->fields(),
                'changes' => $changeSet->toAuditArray(),
            ],
        ]);
    }
}
