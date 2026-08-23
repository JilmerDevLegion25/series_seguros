<?php

namespace App\Enums;

enum MotoInformationSource: string
{
    case ASESOR_COMERCIAL = 'ASESOR_COMERCIAL';
    case NEGOCIADOR_CARTERA = 'NEGOCIADOR_CARTERA';
    case CORREDOR_DE_SEGURO = 'CORREDOR_DE_SEGURO';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ASESOR_COMERCIAL->value => 'Asesor comercial',
            self::NEGOCIADOR_CARTERA->value => 'Negociador cartera',
            self::CORREDOR_DE_SEGURO->value => 'Corredor de seguro',
        ];
    }
}
