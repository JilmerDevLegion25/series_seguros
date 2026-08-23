<?php

namespace App\Enums;

enum ActivityType: string
{
    case CREATED = 'CREATED';
    case UPDATED = 'UPDATED';
    case OWNER_REASSIGNED = 'OWNER_REASSIGNED';
    case RESPONSE_OBTAINED = 'RESPONSE_OBTAINED';
}
