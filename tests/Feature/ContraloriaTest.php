<?php

namespace Tests\Feature;

use App\Services\BuscadorCGRService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pruebas de la API de Contraloría con respuestas SIMULADAS (no se consulta a la Contraloría real).
 */
class ContraloriaTest extends TestCase
{
    private const SERVICIO = 'appbp.contraloria.gob.pe/*';

    /** Construye una fila como las que devuelve BuscadorCGR. */
    private function fila(string $codigo, string $numero, string $descripcion, string $publicacion = '2026/05/22', array $extra = []): array
    {
        return array_merge([
            'id' => null,
            'CodigoInforme' => $codigo,
            'Departamento' => 'LAMBAYEQUE',
            'Provincia' => 'LAMBAYEQUE',
            'Distrito' => 'MORROPE',
            'Descripcion' => $descripcion,
            'Entidad' => 'GERENCIA REGIONAL DE TRANSPORTES Y COMUNICACIONES DE LAMBAYEQUE',
            'FechaEmision' => '2026/05/21',
            'FechaPublicacion' => $publicacion,
            'FechaFinEjecucion' => '2026/05/28',
            'ModalidadServicio' => 'CONTROL CONCURRENTE',
            'ServicioControl' => 'SERVICIO CONTROL SIMULTANEO',
            'NumeroInforme' => $numero,
            'Sector' => 'GOBIERNOS REGIONALES',
            'Periodo' => '2026',
            'NivelGobierno' => 'GOBIERNO REGIONAL',
            'RutaCloudInforme' => 'http://apps8.contraloria.gob.pe/SPIC/srvDownload/ViewPDF?CRES_CODIGO=' . $codigo . '&TIPOARCHIVO=ADJUNTO',
            'RutaCloudResumen' => 'http://apps8.contraloria.gob.pe/SPIC/srvDownload/ViewPDF?CRES_CODIGO=' . $codigo . '&TIPOARCHIVO=RE',
            'Funcionarios' => 'NOMBRE DE PERSONA DE PRUEBA',
            'TotalRows' => '1',
            'TotalPages' => '1',
        ], $extra);
    }

    private function urlApi(string $cui): string
    {
        return '/api/contraloria/obras/' . $cui . '/informes';
    }

    public function test_devuelve_solo_los_informes_que_citan_el_cui_exacto(): void
    {
        Http::fake([self::SERVICIO => Http::response([
            $this->fila('2026CPO345100006', '006-2026-2-3451', 'Ejecución de la obra X, con CUI N° 2650667', '2026/06/02'),
            $this->fila('2026CSI345100005', '005-2026-OCI/3451-SCC', 'Ejecución de la obra X, con CUI N° 2650667', '2026/05/22'),
            $this->fila('2026CSI345100003', '003-2026-OCI/3451-SCC', 'Obra X - CUI 2650667', '2026/04/15'),
            // Deben descartarse:
            $this->fila('RUIDO-1', '010-2026', 'Otra obra, con CUI N° 26506671'),
            $this->fila('RUIDO-2', '011-2026', 'Servicio de CUIDADO DIURNO 2650667'),
            $this->fila('RUIDO-3', '012-2026', 'Oficio 2650667 sin relación con ninguna obra'),
        ])]);

        $respuesta = $this->getJson($this->urlApi('2650667'));

        $respuesta->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cui', '2650667')
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'informes')
            ->assertJsonPath('informes.0.codigo', '2026CPO345100006') // el más reciente primero
            ->assertJsonPath('informes.0.relacion_cui', 'confirmada')
            ->assertJsonPath('informes.0.fecha_publicacion', '2026-06-02');

        $codigos = collect($respuesta->json('informes'))->pluck('codigo')->all();
        $this->assertEqualsCanonicalizing(
            ['2026CPO345100006', '2026CSI345100005', '2026CSI345100003'],
            $codigos
        );
    }

    public function test_no_devuelve_nombres_de_funcionarios(): void
    {
        Http::fake([self::SERVICIO => Http::response([
            $this->fila('2026CSIL43000144', '20588-2026-CG/GRLA-SCP', 'Ejecución de la obra IOARR, DEPARTAMENTO LAMBAYEQUE - CUI 2518752'),
        ])]);

        $respuesta = $this->getJson($this->urlApi('2518752'));

        $respuesta->assertOk()->assertJsonPath('total', 1);
        $this->assertStringNotContainsString('PERSONA DE PRUEBA', $respuesta->getContent());
    }

    public function test_sin_resultados_responde_200_con_lista_vacia(): void
    {
        Http::fake([self::SERVICIO => Http::response([])]);

        $this->getJson($this->urlApi('9999999'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'informes');
    }

    public function test_error_de_conexion_responde_503_y_no_es_lo_mismo_que_sin_resultados(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Tiempo agotado (simulado)');
        });

        $this->getJson($this->urlApi('2650667'))
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.codigo', 'FUENTE_NO_DISPONIBLE');
    }

    public function test_error_http_de_la_contraloria_responde_502(): void
    {
        Http::fake([self::SERVICIO => Http::response('Error interno', 500)]);

        $this->getJson($this->urlApi('2650667'))
            ->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.codigo', 'FUENTE_RESPUESTA_INVALIDA');
    }

    public function test_respuesta_que_no_es_una_lista_json_responde_502(): void
    {
        Http::fake([self::SERVICIO => Http::response('<html>Mantenimiento</html>', 200)]);

        $this->getJson($this->urlApi('2650667'))
            ->assertStatus(502)
            ->assertJsonPath('error.codigo', 'FUENTE_RESPUESTA_INVALIDA');
    }

    public function test_cui_invalido_responde_422_sin_consultar_a_la_contraloria(): void
    {
        Http::fake();

        foreach (['123', '12345678', 'abc1234', '26506a7'] as $cui) {
            $this->getJson($this->urlApi($cui))
                ->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error.codigo', 'CUI_INVALIDO');
        }

        Http::assertNothingSent();
    }

    public function test_la_segunda_consulta_del_mismo_cui_sale_de_la_cache(): void
    {
        Http::fake([self::SERVICIO => Http::response([
            $this->fila('2026CSIL43000144', '20588-2026-CG/GRLA-SCP', 'Obra IOARR - CUI 2518752'),
        ])]);

        $this->getJson($this->urlApi('2518752'))->assertOk()->assertJsonPath('total', 1);
        $this->getJson($this->urlApi('2518752'))->assertOk()->assertJsonPath('total', 1);

        Http::assertSentCount(1);
    }

    public function test_recorre_varias_paginas(): void
    {
        $pagina1 = [$this->fila('A-1', '001-2026', 'Obra Y - CUI 2518752', '2026/03/01', ['TotalPages' => '2'])];
        for ($i = 2; $i <= 50; $i++) {
            $pagina1[] = $this->fila('RUIDO-' . $i, '9' . $i . '-2026', 'Otra obra, con CUI N° 25187521', '2026/01/01', ['TotalPages' => '2']);
        }
        $pagina2 = [$this->fila('B-1', '002-2026', 'Obra Y - CUI 2518752', '2026/04/01', ['TotalPages' => '2'])];

        Http::fake([self::SERVICIO => Http::sequence()->push($pagina1)->push($pagina2)]);

        $this->getJson($this->urlApi('2518752'))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('parcial', false);

        Http::assertSentCount(2);
    }

    public function test_regla_estricta_del_cui(): void
    {
        $casos = [
            ['Obra X - CUI 2518752', '2518752', true],
            ['Obra X, con CUI N° 2650667', '2650667', true],
            ['Obra X, con CUI n.° 2616403', '2616403', true],
            ['Obra X, con CUI N° 2111111 y 2650667', '2650667', true],
            ['Obra X, C.U.I. 2650667', '2650667', true],
            ['Obra X, código único de inversión 2650667', '2650667', true],
            ['Obra X, con CUI N° 26506671', '2650667', false], // un dígito de más
            ['Obra X, con CUI N° 12650667', '2650667', false], // un dígito de más al inicio
            ['Obra X, con CUI N° 2650667', '265066', false],   // CUI incompleto
            ['Servicio de CUIDADO DIURNO 2650667', '2650667', false],
            ['Oficio 2650667 sin la palabra clave', '2650667', false],
        ];

        foreach ($casos as [$texto, $cui, $esperado]) {
            $this->assertSame($esperado, BuscadorCGRService::coincideCui($texto, $cui), "Falló con: {$texto}");
        }
    }
}
