<?php

namespace App\Enums;

enum ImportRowStatus: string
{
    case SUCCESS = 'SUCCESS';
    case REJECTED = 'REJECTED';
}
