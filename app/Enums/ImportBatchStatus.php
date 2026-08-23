<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case COMPLETED_WITH_ERRORS = 'COMPLETED_WITH_ERRORS';
    case FAILED = 'FAILED';
}
