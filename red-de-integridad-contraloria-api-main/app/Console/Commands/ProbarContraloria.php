<?php

namespace App\Console\Commands;

use App\Services\BuscadorCGRService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * FASE 3 - Prueba de conexión.
 * Hace UNA sola consulta a BuscadorCGR para comprobar que Laravel
 * puede recibir JSON. No es todavía el servicio definitivo.
 *
 * Uso:  php artisan contraloria:probar 2518752
 */
class ProbarContraloria extends Command
{
    protected $signature = 'contraloria:probar {cui=2518752 : CUI a consultar}';

    protected $description = 'Prueba de conexión: una sola consulta a BuscadorCGR (Contraloría)';

    private const URL = 'https://appbp.contraloria.gob.pe/BuscadorCGR/Informes/BusquedaInformesCGR.ashx';

    public function handle(): int
    {
        $cui = (string) $this->argument('cui');

        $this->info("Consultando BuscadorCGR con pGeneral={$cui} ...");

        try {
            // asForm() = enviar como formulario (application/x-www-form-urlencoded),
            // igual que hace la página web de la Contraloría.
            $respuesta = Http::asForm()
                ->timeout(20)
                ->withHeaders([
                    'User-Agent'       => 'RedIntegridadLambayeque-UniversidadSipan/1.0 (proyecto universitario)',
                    'Referer'          => 'https://appbp.contraloria.gob.pe/BuscadorCGR/Informes/Avanzado.html',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post(self::URL, [
                    'ActionPage'            => 'TransportType',
                    'Action'                => 'loadInformesElastic',
                    'PageSize'              => '50',
                    'PageNumber'            => '1',
                    'pGeneral'              => $cui,
                    'pAnio'                 => '',
                    'pFechaInicio'          => '01/01/1800',
                    'pFechaHasta'           => '01/01/2500',
                    'pEntidad'              => '',
                    'pSector'               => '',
                    'pModalidadServicio'    => 'NOT "REPORTE DE AVANCE"',
                    'pReconstruccion'       => '',
                    'pReconstruccionDepa'   => '',
                    'pServicio'             => '',
                    'pDepartamento'         => '',
                    'pResponsabilidad'      => '',
                    'pCovid'                => '',
                    'pCovidDepa'            => '',
                    'pNivelGobierno'        => '',
                    'pProvincia'            => '',
                    'pDistrito'             => '',
                    'pAnioFinEjec'          => '',
                    'pEsConResponsabilidad' => '',
                    'pEvento'               => '',
                    'pOperativo'            => '',
                    'pFechaInicioEjec'      => '01/01/1800',
                    'pFechaFinEjec'         => '01/01/3000',
                ]);
        } catch (\Throwable $e) {
            // Aquí caen: sin internet, tiempo agotado, fallo de DNS o de certificado.
            $this->error('Error de conexión: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line('HTTP: ' . $respuesta->status());
        $this->line('Content-Type: ' . $respuesta->header('Content-Type'));

        if ($respuesta->failed()) {
            $this->error('La Contraloría respondió con error HTTP.');

            return self::FAILURE;
        }

        $informes = $respuesta->json();

        if (! is_array($informes)) {
            $this->error('La respuesta no es un JSON válido. Primeros caracteres:');
            $this->line(substr($respuesta->body(), 0, 300));

            return self::FAILURE;
        }

        $this->info('Informes recibidos: ' . count($informes));

        foreach ($informes as $informe) {
            $titulo = (string) ($informe['Descripcion'] ?? '');

            // Misma regla estricta del servicio (Fase 4): CUI completo, tras la palabra "CUI".
            $cuiExacto = BuscadorCGRService::coincideCui($titulo, $cui);

            $this->line(sprintf(
                ' - %s | N° %s | publicado %s | CUI exacto en el título: %s',
                $informe['CodigoInforme'] ?? '?',
                $informe['NumeroInforme'] ?? '?',
                $informe['FechaPublicacion'] ?? '?',
                $cuiExacto ? 'SÍ' : 'no'
            ));
        }

        return self::SUCCESS;
    }
}
