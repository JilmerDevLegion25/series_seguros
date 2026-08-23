<?php

namespace App\Enums;

enum CancellationOrigin: string
{
    case PUBLIC = 'PUBLIC';
    case ADVISOR = 'ADVISOR';
}
