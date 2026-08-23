<?php

namespace App\Authorization;

use App\Enums\PermissionKey;

final readonly class PermissionCatalogue
{
    /**
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            PermissionKey::CANCELLATIONS_VIEW->value => 'View cancellations',
            PermissionKey::CANCELLATIONS_CREATE->value => 'Create cancellations',
            PermissionKey::CANCELLATIONS_UPDATE->value => 'Update cancellations',
            PermissionKey::CANCELLATIONS_REASSIGN->value => 'Reassign cancellations',
            PermissionKey::CANCELLATIONS_ACTIVITY_VIEW->value => 'View cancellation activity',
            PermissionKey::RESPONSES_IMPORT->value => 'Import responses',
            PermissionKey::CANCELLATIONS_EXPORT->value => 'Export cancellations',
            PermissionKey::RADICADO_SMS_RETRY->value => 'Retry radicado SMS',
            PermissionKey::ADVISOR_ACCOUNTS_VIEW->value => 'View Advisor accounts',
            PermissionKey::ADVISOR_ACCOUNTS_CREATE->value => 'Create Advisor accounts',
            PermissionKey::ADVISOR_ACCOUNTS_UPDATE->value => 'Update Advisor accounts',
            PermissionKey::ADVISOR_ACCOUNTS_RESET_PASSWORD->value => 'Reset Advisor account password',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return list<PermissionKey>
     */
    public static function advisorOnlyKeys(): array
    {
        return [
            PermissionKey::CANCELLATIONS_UPDATE,
            PermissionKey::CANCELLATIONS_REASSIGN,
            PermissionKey::CANCELLATIONS_ACTIVITY_VIEW,
            PermissionKey::RESPONSES_IMPORT,
            PermissionKey::CANCELLATIONS_EXPORT,
            PermissionKey::RADICADO_SMS_RETRY,
            PermissionKey::ADVISOR_ACCOUNTS_VIEW,
            PermissionKey::ADVISOR_ACCOUNTS_CREATE,
            PermissionKey::ADVISOR_ACCOUNTS_UPDATE,
            PermissionKey::ADVISOR_ACCOUNTS_RESET_PASSWORD,
        ];
    }

    public static function isAdvisorOnly(PermissionKey $permissionKey): bool
    {
        return in_array($permissionKey, self::advisorOnlyKeys(), strict: true);
    }
}
