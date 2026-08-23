<?php

namespace App\Actions\Cancellations\Credit;

use App\Actions\Cancellations\Concerns\AuthorizesCancellationMutations;
use App\DTOs\Cancellations\CancellationMutationResult;
use App\DTOs\Cancellations\Credit\UpdateCreditCancellationData;
use App\Enums\ActivityType;
use App\Enums\AuditEventType;
use App\Enums\CancellationStatus;
use App\Enums\CancellationType;
use App\Enums\PermissionKey;
use App\Exceptions\CancellationMutationException;
use App\Models\Activity;
use App\Models\Audit;
use App\Models\CreditCancellation;
use App\Models\User;
use App\Support\Changes\ChangeSet;
use App\Support\Security\SensitiveValueSanitizer;
use BackedEnum;
use Illuminate\Support\Facades\DB;

final class UpdateCreditCancellationAction
{
    use AuthorizesCancellationMutations;

    public function execute(
        CreditCancellation $credit,
        UpdateCreditCancellationData $data,
        User $actor,
        ?string $requestId = null,
    ): CancellationMutationResult {
        $this->assertAdvisorCan($actor, PermissionKey::CANCELLATIONS_UPDATE);

        return DB::transaction(function () use ($credit, $data, $actor, $requestId): CancellationMutationResult {
            /** @var CreditCancellation $lockedCredit */
            $lockedCredit = CreditCancellation::query()
                ->whereKey($credit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertMutable($lockedCredit, $data->expectedVersion);

            $after = $this->targetFields($lockedCredit, $data->fields);
            $before = $this->snapshot($lockedCredit, array_keys($after));
            $changeSet = ChangeSet::fromArrays($before, $after);

            if ($changeSet->isEmpty()) {
                return new CancellationMutationResult($lockedCredit, false, $changeSet);
            }

            $lockedCredit->forceFill(array_merge($after, [
                'version' => $lockedCredit->version + 1,
            ]))->save();

            $this->recordMutation($lockedCredit, $actor, $data->reason, $changeSet, $requestId);

            return new CancellationMutationResult($lockedCredit, true, $changeSet);
        });
    }

    /**
     * @param  array<string, bool|string|null>  $fields
     * @return array<string, bool|string|null>
     */
    private function targetFields(CreditCancellation $credit, array $fields): array
    {
        $cancelPersonalAccidents = array_key_exists('cancel_personal_accidents', $fields)
            ? (bool) $fields['cancel_personal_accidents']
            : $credit->cancel_personal_accidents;
        $cancelUnemploymentInsurance = array_key_exists('cancel_unemployment_insurance', $fields)
            ? (bool) $fields['cancel_unemployment_insurance']
            : $credit->cancel_unemployment_insurance;

        if (! $cancelPersonalAccidents && ! $cancelUnemploymentInsurance) {
            throw CancellationMutationException::invalidData('CREDIT_INSURANCE_REQUIRED');
        }

        return $fields;
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

    /**
     * @param  list<string>  $fields
     * @return array<string, bool|string|null>
     */
    private function snapshot(CreditCancellation $credit, array $fields): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            $snapshot[$field] = $this->scalarValue($credit->getAttribute($field));
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
        CreditCancellation $credit,
        User $actor,
        string $reason,
        ChangeSet $changeSet,
        ?string $requestId,
    ): void {
        Activity::query()->create([
            'credit_cancellation_id' => $credit->id,
            'actor_user_id' => $actor->id,
            'type' => ActivityType::UPDATED,
            'metadata' => [
                'event' => AuditEventType::CANCELLATION_UPDATED->value,
                'type' => CancellationType::CREDIT->value,
                'radicado' => $credit->radicado,
                'version' => $credit->version,
                'changed_fields' => $changeSet->fields(),
            ],
        ]);

        Audit::query()->create([
            'credit_cancellation_id' => $credit->id,
            'actor_user_id' => $actor->id,
            'event_type' => AuditEventType::CANCELLATION_UPDATED,
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
