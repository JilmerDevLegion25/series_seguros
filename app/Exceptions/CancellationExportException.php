<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class CancellationExportException extends RuntimeException
{
    public static function rowLimitExceeded(int $maxRows): self
    {
        return new self("La exportacion supera el maximo de {$maxRows} filas.");
    }

    public static function missingCancellationType(): self
    {
        return new self('Selecciona el tipo de seguro a exportar.');
    }

    public static function writeFailed(Throwable $previous): self
    {
        return new self('No fue posible generar el archivo de exportacion.', previous: $previous);
    }
}
