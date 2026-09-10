<?php

namespace App\Enums;

enum CancellationStatus: string
{
    case EN_GESTION = 'EN_GESTION';
    case PENDIENTE_RADICACION = 'PENDIENTE_RADICACION';
    case RESPUESTA_OBTENIDA = 'RESPUESTA_OBTENIDA';
}
