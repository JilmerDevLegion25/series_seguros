<?php

namespace App\Enums;

enum PermissionKey: string
{
    case CANCELLATIONS_VIEW = 'cancellations.view';
    case CANCELLATIONS_CREATE = 'cancellations.create';
    case CANCELLATIONS_UPDATE = 'cancellations.update';
    case CANCELLATIONS_REASSIGN = 'cancellations.reassign';
    case CANCELLATIONS_ACTIVITY_VIEW = 'cancellations.activity.view';
    case RESPONSES_IMPORT = 'responses.import';
    case CANCELLATIONS_EXPORT = 'cancellations.export';
    case RADICADO_SMS_RETRY = 'radicado_sms.retry';
    case ADVISOR_ACCOUNTS_VIEW = 'advisor_accounts.view';
    case ADVISOR_ACCOUNTS_CREATE = 'advisor_accounts.create';
    case ADVISOR_ACCOUNTS_UPDATE = 'advisor_accounts.update';
    case ADVISOR_ACCOUNTS_RESET_PASSWORD = 'advisor_accounts.reset_password';
}
