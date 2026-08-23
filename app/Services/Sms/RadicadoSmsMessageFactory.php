<?php

namespace App\Services\Sms;

use App\Models\CreditCancellation;
use App\Models\MotoCancellation;

final readonly class RadicadoSmsMessageFactory
{
    public function makeForMoto(MotoCancellation $moto): string
    {
        return "Solicitud enviada. RADICADO {$moto->radicado}. Series Seguros";
    }

    public function makeForCredit(CreditCancellation $credit): string
    {
        return "Solicitud enviada. RADICADO {$credit->radicado}. Series Seguros";
    }
}
