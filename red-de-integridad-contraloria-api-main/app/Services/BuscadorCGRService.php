<?php

namespace App\Services;

use App\Exceptions\ContraloriaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta el Buscador de Informes de Servicios de Control (BuscadorCGR) de la Contraloría
 * y devuelve SOLO los informes cuyo título cita el CUI exacto de la obra.
 *
 * Por qué hay dos filtros:
 *  1) La Contraloría busca por prefijo en el texto (pGeneral): "25187" también trae "2518752".
 *  2) Este servicio revisa cada informe y solo acepta el CUI completo, sin dígitos pegados.
 */
class BuscadorCGRService
{
    public const FUENTE = 'Contraloría General de la República - BuscadorCGR';

    /**
     * Busca la palabra CUI (o "C.U.I.", o "Código Único de Inversión") y captura los números
     * que le siguen (uno o varios: "CUI N° 2111111 y 2650667").
     */
    private const PATRON_CUI = '/(?:\bCUI\b|\bC\.U\.I\b\.?|\bC[ÓO]DIGO\s+[ÚU]NICO(?:\s+DE\s+INVERSI[ÓO]N(?:ES)?)?)[^0-9]{0,15}((?:\d{6,8}(?:\s*(?:,|;|\by\b|\be\b|\/|-)\s*)?)+)/iu';

    /**
     * @return array{total:int, parcial:bool, consultado_en:string, informes:array<int,array<string,mixed>>}
     *
     * @throws ContraloriaException si no se pudo consultar a la Contraloría
     */
    public function informesPorCui(string $cui): array
    {
        $ttl = (int) config('services.contraloria.cache_ttl');

        if ($ttl <= 0) {
            return $this->consultar($cui);
        }

        // Si consultar() lanza una excepción, Laravel NO guarda nada en caché.
        return Cache::remember('contraloria:informes:' . $cui, $ttl, fn () => $this->consultar($cui));
    }

    /**
     * Regla estricta: ¿el texto cita este CUI exacto después de la palabra "CUI"?
     */
    public static function coincideCui(string $texto, string $cui): bool
    {
        if (! preg_match_all(self::PATRON_CUI, $texto, $coincidencias)) {
            return false;
        }

        foreach ($coincidencias[1] as $lista) {
            preg_match_all('/\d+/', $lista, $numeros);

            if (in_array($cui, $numeros[0], true)) {
                return true;
            }
        }

        return false;
    }

    private function consultar(string $cui): array
    {
        $tamano = (int) config('services.contraloria.page_size');
        $maxPaginas = max(1, (int) config('services.contraloria.max_pages'));

        $informes = [];
        $parcial = false;

        for ($pagina = 1; $pagina <= $maxPaginas; $pagina++) {
            $filas = $this->pedirPagina($cui, $pagina, $tamano);

            foreach ($filas as $fila) {
                // Solo el título (Descripcion) puede demostrar la relación con el CUI.
                if (is_array($fila) && self::coincideCui((string) ($fila['Descripcion'] ?? ''), $cui)) {
                    $informe = $this->normalizar($fila);
                    $informes[$informe['codigo'] ?? 'sin-codigo-' . count($informes)] = $informe;
                }
            }

            $primera = $filas[0] ?? [];
            $totalPaginas = is_array($primera) ? (int) ($primera['TotalPages'] ?? 1) : 1;
            $hayMas = count($filas) >= $tamano && $pagina < $totalPaginas;

            if (! $hayMas) {
                break;
            }

            if ($pagina === $maxPaginas) {
                $parcial = true; // quedaron páginas sin revisar
            }
        }

        $informes = array_values($informes);

        // Más recientes primero (por fecha de publicación).
        usort($informes, fn (array $a, array $b) => strcmp((string) $b['fecha_publicacion'], (string) $a['fecha_publicacion']));

        return [
            'total' => count($informes),
            'parcial' => $parcial,
            'consultado_en' => now()->toIso8601String(),
            'informes' => $informes,
        ];
    }

    /**
     * Pide UNA página a la Contraloría y devuelve la lista de filas (puede ser []).
     */
    private function pedirPagina(string $cui, int $pagina, int $tamano): array
    {
        $config = config('services.contraloria');

        try {
            // asForm(): se envía como formulario, igual que la página de la Contraloría.
            $respuesta = Http::asForm()
                ->timeout((int) $config['timeout'])
                ->retry(2, 250, throw: false)
                ->withHeaders([
                    'User-Agent' => $config['user_agent'],
                    'Referer' => $config['referer'],
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post($config['url'], $this->parametros($cui, $pagina, $tamano));
        } catch (ConnectionException $e) {
            Log::error('[Contraloría] No hubo conexión con BuscadorCGR', [
                'cui' => $cui,
                'pagina' => $pagina,
                'error' => $e->getMessage(),
            ]);

            throw new ContraloriaException(
                'No se pudo conectar con el Buscador de la Contraloría. Intente nuevamente más tarde.',
                'FUENTE_NO_DISPONIBLE',
                503,
                $e
            );
        }

        if ($respuesta->failed()) {
            Log::warning('[Contraloría] BuscadorCGR respondió con error HTTP', [
                'cui' => $cui,
                'pagina' => $pagina,
                'status' => $respuesta->status(),
            ]);

            throw new ContraloriaException(
                'El Buscador de la Contraloría respondió con un error (HTTP ' . $respuesta->status() . ').',
                'FUENTE_RESPUESTA_INVALIDA',
                502
            );
        }

        $datos = $respuesta->json();

        // Sin resultados = lista vacía []. Cualquier otra cosa que no sea una lista es inválida.
        if (! is_array($datos) || ! array_is_list($datos)) {
            Log::warning('[Contraloría] BuscadorCGR devolvió una respuesta que no es una lista JSON', [
                'cui' => $cui,
                'pagina' => $pagina,
                'inicio' => mb_substr($respuesta->body(), 0, 200),
            ]);

            throw new ContraloriaException(
                'El Buscador de la Contraloría devolvió una respuesta inesperada.',
                'FUENTE_RESPUESTA_INVALIDA',
                502
            );
        }

        return $datos;
    }

    private function parametros(string $cui, int $pagina, int $tamano): array
    {
        return [
            'ActionPage' => 'TransportType',
            'Action' => 'loadInformesElastic',
            'PageSize' => (string) $tamano,
            'PageNumber' => (string) $pagina,
            'pGeneral' => $cui,
            'pAnio' => '',
            'pFechaInicio' => '01/01/1800',
            'pFechaHasta' => '01/01/2500',
            'pEntidad' => '',
            'pSector' => '',
            'pModalidadServicio' => 'NOT "REPORTE DE AVANCE"',
            'pReconstruccion' => '',
            'pReconstruccionDepa' => '',
            'pServicio' => '',
            'pDepartamento' => '',
            'pResponsabilidad' => '',
            'pCovid' => '',
            'pCovidDepa' => '',
            'pNivelGobierno' => '',
            'pProvincia' => '',
            'pDistrito' => '',
            'pAnioFinEjec' => '',
            'pEsConResponsabilidad' => '',
            'pEvento' => '',
            'pOperativo' => '',
            'pFechaInicioEjec' => '01/01/1800',
            'pFechaFinEjec' => '01/01/3000',
        ];
    }

    /**
     * Convierte una fila de la Contraloría a nuestro formato.
     * Campo "Funcionarios" (nombres de personas): NO se incluye.
     */
    private function normalizar(array $fila): array
    {
        return [
            'codigo' => $this->texto($fila['CodigoInforme'] ?? null),
            'numero_informe' => $this->texto($fila['NumeroInforme'] ?? null),
            'descripcion' => $this->texto($fila['Descripcion'] ?? null),
            'entidad' => $this->texto($fila['Entidad'] ?? null),
            'departamento' => $this->texto($fila['Departamento'] ?? null),
            'provincia' => $this->texto($fila['Provincia'] ?? null),
            'distrito' => $this->texto($fila['Distrito'] ?? null),
            'sector' => $this->texto($fila['Sector'] ?? null),
            'nivel_gobierno' => $this->texto($fila['NivelGobierno'] ?? null),
            'periodo' => $this->texto($fila['Periodo'] ?? null),
            'modalidad_servicio' => $this->texto($fila['ModalidadServicio'] ?? null),
            'servicio_control' => $this->texto($fila['ServicioControl'] ?? null),
            'fecha_emision' => $this->fecha($fila['FechaEmision'] ?? null),
            'fecha_publicacion' => $this->fecha($fila['FechaPublicacion'] ?? null),
            'fecha_fin_ejecucion' => $this->fecha($fila['FechaFinEjecucion'] ?? null),
            'url_informe' => $this->enlace($fila['RutaCloudInforme'] ?? null) ?? $this->enlace($fila['ResumenInforme'] ?? null),
            'url_resumen' => $this->enlace($fila['RutaCloudResumen'] ?? null) ?? $this->enlace($fila['ResumenEjecutivo'] ?? null),
            'relacion_cui' => 'confirmada',
        ];
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }

    /** "2026/11/06" -> "2026-11-06" (o null si no tiene ese formato). */
    private function fecha(mixed $valor): ?string
    {
        if (is_string($valor) && preg_match('#^(\d{4})[/-](\d{2})[/-](\d{2})#', $valor, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return null;
    }

    /** Solo se aceptan enlaces http/https. */
    private function enlace(mixed $valor): ?string
    {
        $valor = $this->texto($valor);

        return ($valor !== null && preg_match('#^https?://#i', $valor)) ? $valor : null;
    }
}
