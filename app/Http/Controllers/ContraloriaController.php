<?php

namespace App\Http\Controllers;

use App\Exceptions\ContraloriaException;
use App\Services\BuscadorCGRService;
use Illuminate\Http\JsonResponse;

class ContraloriaController extends Controller
{
    /**
     * GET /api/contraloria/obras/{cui}/informes
     *
     * Devuelve los informes de control cuyo título cita el CUI exacto.
     */
    public function informes(string $cui, BuscadorCGRService $servicio): JsonResponse
    {
        $min = (int) config('services.contraloria.cui_min_digitos');
        $max = (int) config('services.contraloria.cui_max_digitos');

        // \z = fin exacto del texto (evita que un salto de línea al final pase la validación).
        if (! preg_match('/^\d{' . $min . ',' . $max . '}\z/', $cui)) {
            return $this->error(
                'CUI_INVALIDO',
                "El CUI debe contener solo dígitos ({$min} a {$max} dígitos).",
                422
            );
        }

        try {
            $resultado = $servicio->informesPorCui($cui);
        } catch (ContraloriaException $e) {
            return $this->error($e->codigoError, $e->getMessage(), $e->estadoHttp);
        }

        $aviso = $resultado['total'] === 0
            ? 'No se encontraron informes que citen este CUI en el Buscador de la Contraloría. Esto no implica que la obra no tenga informes de control.'
            : 'Solo se incluyen informes cuyo título cita este CUI. Pueden existir otros informes sin relación demostrada.';

        return $this->json([
            'success' => true,
            'fuente' => BuscadorCGRService::FUENTE,
            'cui' => $cui,
            'total' => $resultado['total'],
            'parcial' => $resultado['parcial'],
            'aviso' => $aviso,
            'consultado_en' => $resultado['consultado_en'],
            'informes' => $resultado['informes'],
        ]);
    }

    private function error(string $codigo, string $mensaje, int $estado): JsonResponse
    {
        return $this->json([
            'success' => false,
            'fuente' => BuscadorCGRService::FUENTE,
            'error' => [
                'codigo' => $codigo,
                'mensaje' => $mensaje,
            ],
        ], $estado);
    }

    private function json(array $datos, int $estado = 200): JsonResponse
    {
        return response()->json($datos, $estado, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
