<?php

namespace App\Enums;

enum SmsAttemptStatus: string
{
    case PENDING = 'PENDING';
    case SENT = 'SENT';
    case FAILED = 'FAILED';
    case UNKNOWN = 'UNKNOWN';
}
