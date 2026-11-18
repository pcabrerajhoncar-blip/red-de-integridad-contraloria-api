<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Error al consultar la Contraloría (no confundir con "sin resultados").
 *
 * - FUENTE_NO_DISPONIBLE (503): no hubo conexión, tiempo agotado, fallo de DNS/certificado.
 * - FUENTE_RESPUESTA_INVALIDA (502): la Contraloría respondió, pero con error HTTP o con algo que no es la lista JSON esperada.
 */
class ContraloriaException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $codigoError = 'FUENTE_NO_DISPONIBLE',
        public readonly int $estadoHttp = 503,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
