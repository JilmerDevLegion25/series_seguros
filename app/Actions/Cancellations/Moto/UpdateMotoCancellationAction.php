<?php

namespace App\Actions\Cancellations\Moto;

use App\Actions\Cancellations\Concerns\AuthorizesCancellationMutations;
use App\DTOs\Cancellations\CancellationMutationResult;
use App\DTOs\Cancellations\Moto\UpdateMotoCancellationData;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Exceptions\CancellationMutationException;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\MotoCancellation;
use App\Models\User;
use App\Support\Changes\ChangeSet;
use App\Support\Security\SensitiveValueSanitizer;
use BackedEnum;
use Illuminate\Support\Facades\DB;

final class UpdateMotoCancellationAction
{
    use AuthorizesCancellationMutations;

    public function execute(
        MotoCancellation $moto,
        UpdateMotoCancellationData $data,
        User $actor,
        ?string $requestId = null,
    ): CancellationMutationResult {
        $this->assertAdvisorCan($actor, PermissionKey::CANCELLATIONS_UPDATE);

        return DB::transaction(function () use ($moto, $data, $actor, $requestId): CancellationMutationResult {
            /** @var MotoCancellation $lockedMoto */
            $lockedMoto = MotoCancellation::query()
                ->whereKey($moto->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMutable($lockedMoto, $data->expectedVersion);

            $after = $this->targetFields($lockedMoto, $data->fields);
            $before = $this->snapshot($lockedMoto, array_keys($after));
            $changeSet = ChangeSet::fromArrays($before, $after);

            if ($changeSet->isEmpty()) {
                return new CancellationMutationResult($lockedMoto, false, $changeSet);
            }

            $lockedMoto->forceFill(array_merge($after, [
                'version' => $lockedMoto->version + 1,
            ]))->save();

            $this->recordMutation($lockedMoto, $actor, $data->reason, $changeSet, $requestId);

            return new CancellationMutationResult($lockedMoto, true, $changeSet);
        });
    }

    /**
     * @param  array<string, bool|string|null>  $fields
     * @return array<string, bool|string|null>
     */
    private function targetFields(MotoCancellation $moto, array $fields): array
    {
        $target = $fields;
        $isCreditHolder = array_key_exists('is_credit_holder', $target)
            ? (bool) $target['is_credit_holder']
            : $moto->is_credit_holder;

        // if ($isCreditHolder) {
        //     $target['credit_owner_name'] = null;
        //     $target['credit_owner_cedula'] = null;
        // }

        $creditOwnerName = array_key_exists('credit_owner_name', $target)
            ? $target['credit_owner_name']
            : $moto->credit_owner_name;
        $creditOwnerCedula = array_key_exists('credit_owner_cedula', $target)
            ? $target['credit_owner_cedula']
            : $moto->credit_owner_cedula;

        if (! $isCreditHolder && ($creditOwnerName === null || $creditOwnerCedula === null)) {
            throw CancellationMutationException::invalidData('CREDIT_OWNER_REQUIRED');
        }

        return $target;
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

    /**
     * @param  list<string>  $fields
     * @return array<string, bool|string|null>
     */
    private function snapshot(MotoCancellation $moto, array $fields): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            $snapshot[$field] = $this->scalarValue($moto->getAttribute($field));
        }

        return $snapshot;
    }

    private function scalarValue(mixed $value): bool|string|null
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_bool($value) || is_string($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    private function recordMutation(
        MotoCancellation $moto,
        User $actor,
        string $reason,
        ChangeSet $changeSet,
        ?string $requestId,
    ): void {
        Activity::query()->create([
            'moto_cancellation_id' => $moto->id,
            'actor_user_id' => $actor->id,
            'type' => ActivityType::UPDATED,
            'metadata' => [
                'event' => AuditEventType::CANCELLATION_UPDATED->value,
                'type' => CancellationType::MOTO->value,
                'radicado' => $moto->radicado,
                'version' => $moto->version,
                'changed_fields' => $changeSet->fields(),
            ],
        ]);

        Audit::query()->create([
            'moto_cancellation_id' => $moto->id,
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::CANCELLATION_UPDATED,
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
