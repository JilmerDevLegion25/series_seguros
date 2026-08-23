<?php

namespace App\Enums;

enum MotoCancellationReason: string
{
    case REDUCIR_GASTOS = 'REDUCIR_GASTOS';
    case DESEA_OTRA_ASEGURADORA = 'DESEA_OTRA_ASEGURADORA';
    case NO_FUE_INFORMADO_DEL_COBRO = 'NO_FUE_INFORMADO_DEL_COBRO';
    case NO_ESTA_INTERESADO = 'NO_ESTA_INTERESADO';
    case INCONFORMIDAD_CON_EL_SERVICIO = 'INCONFORMIDAD_CON_EL_SERVICIO';
    case VENTA_DE_LA_MOTO = 'VENTA_DE_LA_MOTO';
    case PAGO_TOTAL_DE_DEUDA = 'PAGO_TOTAL_DE_DEUDA';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::REDUCIR_GASTOS->value => 'Reducir gastos',
            self::DESEA_OTRA_ASEGURADORA->value => 'Desea otra aseguradora',
            self::NO_FUE_INFORMADO_DEL_COBRO->value => 'No fue informado del cobro',
            self::NO_ESTA_INTERESADO->value => 'No esta interesado',
            self::INCONFORMIDAD_CON_EL_SERVICIO->value => 'Inconformidad con el servicio',
            self::VENTA_DE_LA_MOTO->value => 'Venta de la moto',
            self::PAGO_TOTAL_DE_DEUDA->value => 'Pago total de deuda',
        ];
    }
}
