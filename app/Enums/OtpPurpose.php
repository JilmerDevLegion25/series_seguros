<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case CREATE_MOTO = 'CREATE_MOTO';
    case CREATE_CREDIT = 'CREATE_CREDIT';
    case PASSWORD_RECOVERY = 'PASSWORD_RECOVERY';
}
