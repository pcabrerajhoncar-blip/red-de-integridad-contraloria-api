# API de Informes de Control por CUI (Contraloría)

Módulo de la **Red de Integridad Lambayeque** (proyecto universitario). Recibe el **CUI** (Código Único de Inversiones) de una obra pública y devuelve, en JSON, los **informes de control** de la Contraloría General de la República cuyo título cita ese CUI.

- **Fuente:** [Buscador de Informes de Servicios de Control (BuscadorCGR)](https://appbp.contraloria.gob.pe/BuscadorCGR/Informes/Avanzado.html)
- **Tecnología:** PHP 8.2 o superior, Laravel 12, Laravel HTTP Client.

> **Importante:** BuscadorCGR no ofrece una API pública documentada. Esta API consume el mismo servicio interno que usa su página web. Puede cambiar o dejar de funcionar sin aviso (ver [Limitaciones](#limitaciones-conocidas)).

---

## Cómo funciona (en corto)

1. Se consulta a la Contraloría con el CUI. Su buscador trabaja **por prefijo de texto**: buscar `25187` también trae informes del CUI `2518752`.
2. Este servicio **revisa cada informe** y solo conserva los que cumplen la **regla del CUI exacto**.
3. Se devuelve un JSON uniforme con los informes confirmados.

### Regla del CUI exacto

Un informe se marca `relacion_cui: "confirmada"` solo si su **título** (`Descripcion`) tiene la palabra **CUI** (o `C.U.I.` o "Código Único de Inversión") seguida del número **completo**, sin dígitos pegados antes ni después. Acepta listas ("CUI N° 2111111 y 2650667").

| Título dice... | CUI consultado | Resultado |
|---|---|---|
| `... - CUI 2518752` | 2518752 | Confirmado |
| `... con CUI N° 2650667` | 2650667 | Confirmado |
| `... con CUI n.° 2616403` | 2616403 | Confirmado |
| `... con CUI N° 2111111 y 2650667` | 2650667 | Confirmado |
| `... con CUI N° 26506671` | 2650667 | Descartado (un dígito de más) |
| `... CUIDADO DIURNO 2650667` | 2650667 | Descartado (no es la palabra CUI) |
| `Oficio 2650667 ...` | 2650667 | Descartado (sin la palabra CUI) |

Los informes que no pasan la regla **no se devuelven**. No se asocian informes por parecido de nombre o por ser de la misma entidad.

---

## Requisitos e instalación

Requisitos: **PHP 8.2+** (con las extensiones `curl`, `mbstring`, `openssl`, `fileinfo`, `zip`, `pdo_sqlite`, `sqlite3`) y **Composer**.

```bash
composer install
copy .env.example .env      # en Linux/Mac: cp .env.example .env
php artisan key:generate
php artisan serve
```

La API queda en `http://127.0.0.1:8000`. La página principal (`/`) es una pantalla de prueba con un campo para el CUI.

**Windows con PHP instalado a mano:** hay que crear el `php.ini` (copiando `php.ini-development`), activar las extensiones de arriba y configurar `curl.cainfo` y `openssl.cafile` con un `cacert.pem` (descargado de https://curl.se/ca/cacert.pem). Sin eso aparece el error `cURL error 60: unable to get local issuer certificate`. **No** se debe desactivar la verificación de certificados.

### Configuración (`.env`, opcional)

Los valores por defecto están en `config/services.php` (sección `contraloria`).

| Variable | Por defecto | Qué hace |
|---|---|---|
| `CONTRALORIA_URL` | URL del servicio de BuscadorCGR | Servicio a consultar |
| `CONTRALORIA_TIMEOUT` | `20` | Segundos de espera máxima por consulta |
| `CONTRALORIA_CACHE_TTL` | `3600` | Segundos que se guarda en caché el resultado de un CUI (`0` = sin caché) |
| `CONTRALORIA_MAX_PAGES` | `3` | Máximo de páginas (de 50 informes) por consulta |
| `CACHE_STORE` | `file` | Almacén de caché |

---

## Endpoint

```
GET /api/contraloria/obras/{cui}/informes
```

| Parámetro | Tipo | Descripción |
|---|---|---|
| `cui` | ruta | Solo dígitos, de **6 a 7** dígitos. Ejemplo: `2650667` |

Cabecera recomendada: `Accept: application/json`.

Límite: **30 consultas por minuto por IP** (responde `429` al excederlo).

### Respuesta exitosa (`200`)

Ejemplo ilustrativo (los campos pueden venir en `null` si la Contraloría no los informa):

```json
{
  "success": true,
  "fuente": "Contraloría General de la República - BuscadorCGR",
  "cui": "2518752",
  "total": 1,
  "parcial": false,
  "aviso": "Solo se incluyen informes cuyo título cita este CUI. Pueden existir otros informes sin relación demostrada.",
  "consultado_en": "2026-11-17T19:45:00-05:00",
  "informes": [
    {
      "codigo": "2026CSIL43000144",
      "numero_informe": "20588-2026-CG/GRLA-SCP",
      "descripcion": "EJECUCIÓN DE LA OBRA IOARR: REPARACIÓN DE RESERVORIO; EN EL (LA) EPSEL S.A., DISTRITO DE LA VICTORIA, PROVINCIA CHICLAYO, DEPARTAMENTO LAMBAYEQUE - CUI 2518752",
      "entidad": "EMPRESA PRESTADORA DE SERVICIOS DE SANEAMIENTO DE AGUA POTABLE Y ALCANTARILLADO DE LAMBAYEQUE S.A.",
      "departamento": "LAMBAYEQUE",
      "provincia": "CHICLAYO",
      "distrito": "LA VICTORIA",
      "sector": null,
      "nivel_gobierno": null,
      "periodo": "2026",
      "modalidad_servicio": "CONTROL PREVENTIVO",
      "servicio_control": null,
      "fecha_emision": "2026-11-05",
      "fecha_publicacion": "2026-11-10",
      "fecha_fin_ejecucion": "2026-11-12",
      "url_informe": "http://apps8.contraloria.gob.pe/SPIC/srvDownload/ViewPDF?CRES_CODIGO=2026CSIL43000144&TIPOARCHIVO=ADJUNTO",
      "url_resumen": "http://apps8.contraloria.gob.pe/SPIC/srvDownload/ViewPDF?CRES_CODIGO=2026CSIL43000144&TIPOARCHIVO=RE",
      "relacion_cui": "confirmada"
    }
  ]
}
```

| Campo | Descripción |
|---|---|
| `total` | Cantidad de informes confirmados |
| `parcial` | `true` si la Contraloría tenía más páginas que no se revisaron (límite `CONTRALORIA_MAX_PAGES`) |
| `aviso` | Texto sobre el alcance del resultado. Con `total: 0` aclara que no implica que la obra no tenga informes |
| `consultado_en` | Momento en que se consultó a la Contraloría (con caché puede ser anterior) |
| `informes` | Ordenados por fecha de publicación, del más reciente al más antiguo |
| `fecha_*` | Formato `AAAA-MM-DD` |
| `url_informe`, `url_resumen` | Enlaces a los PDF de la Contraloría (solo `http`/`https`) |
| `relacion_cui` | Siempre `"confirmada"` (ver regla del CUI exacto) |

Para comparar fechas entre fuentes, usar `fecha_publicacion`: la fecha de emisión puede diferir entre BuscadorCGR e Infobras.

### Sin resultados (`200`)

Es una respuesta válida, **no un error**:

```json
{ "success": true, "cui": "9999999", "total": 0, "parcial": false, "informes": [], "aviso": "No se encontraron informes que citen este CUI en el Buscador de la Contraloría. Esto no implica que la obra no tenga informes de control." }
```

### Errores

Todos tienen la misma forma:

```json
{
  "success": false,
  "fuente": "Contraloría General de la República - BuscadorCGR",
  "error": { "codigo": "CUI_INVALIDO", "mensaje": "El CUI debe contener solo dígitos (6 a 7 dígitos)." }
}
```

| HTTP | `error.codigo` | Cuándo |
|---|---|---|
| 422 | `CUI_INVALIDO` | El CUI no son 6 o 7 dígitos |
| 503 | `FUENTE_NO_DISPONIBLE` | No hubo conexión con la Contraloría, tiempo agotado, fallo de DNS o certificado |
| 502 | `FUENTE_RESPUESTA_INVALIDA` | La Contraloría respondió con error HTTP o con algo que no es la lista JSON esperada |
| 429 | (respuesta estándar de Laravel) | Se superó el límite de 30 consultas por minuto |

Los errores 502 y 503 **no se guardan en caché**: la siguiente consulta vuelve a intentar.

---

## Cómo consumirla desde el backend principal (Laravel)

```php
use Illuminate\Support\Facades\Http;

$respuesta = Http::acceptJson()
    ->timeout(30)
    ->get(config('services.contraloria_api.url') . "/api/contraloria/obras/{$cui}/informes");

if ($respuesta->successful()) {
    $datos = $respuesta->json();

    if ($datos['total'] === 0) {
        // Mostrar $datos['aviso'] (no afirmar que la obra "no tiene informes")
    } else {
        foreach ($datos['informes'] as $informe) {
            // $informe['numero_informe'], $informe['descripcion'], $informe['url_informe'], ...
        }
    }
} else {
    // 422, 502, 503, 429: mostrar $respuesta->json('error.mensaje') o un aviso genérico
}
```

Recomendaciones para la web:
- Mostrar siempre `aviso` junto a los resultados y no decir "esta obra no tiene informes" cuando `total` es `0`.
- Diferenciar visualmente "sin resultados" (200 con `total: 0`) de "servicio no disponible" (502/503).
- Mostrar la fuente y la fecha `consultado_en`.

---

## Pruebas

```bash
php artisan test
```

`tests/Feature/ContraloriaTest.php` usa respuestas **simuladas** (no consulta a la Contraloría) y cubre: informes confirmados, descarte de falsos positivos, sin resultados, error de conexión (503), error HTTP (502), respuesta no válida (502), CUI inválido (422), caché, paginación y la regla del CUI exacto.

Prueba manual contra la Contraloría real:

```bash
php artisan contraloria:probar 2650667
```

CUI de referencia verificados el 17/11/2026: `2650667` (4 informes), `2518752` (1 informe), `9999999` (0 informes).

---

## Estructura del proyecto

```
app/
├── Console/Commands/ProbarContraloria.php   # comando de prueba de conexión
├── Exceptions/ContraloriaException.php      # errores de la fuente (502/503)
├── Http/Controllers/ContraloriaController.php  # valida el CUI y arma el JSON
└── Services/BuscadorCGRService.php          # consulta, regla del CUI, caché, logs
routes/api.php                                # GET /api/contraloria/obras/{cui}/informes
config/services.php                           # sección 'contraloria'
resources/views/contraloria/consulta.blade.php  # pantalla de prueba
tests/Feature/ContraloriaTest.php
```

---

## Protección de datos y seguridad

- **No se devuelve el campo `Funcionarios`** (nombres de personas con presunta responsabilidad) que sí trae la fuente. No se almacena información en base de datos: solo caché temporal por CUI.
- Los errores de conexión se registran en `storage/logs/laravel.log` (solo CUI, página y mensaje técnico).
- La verificación de certificados HTTPS permanece activa.
- Por defecto, Laravel permite el acceso desde cualquier origen (CORS) a las rutas `/api/*`. **Antes de publicar la API** conviene restringirlo (`php artisan config:publish cors`) y evaluar una clave de acceso.

---

## Limitaciones conocidas

- **Servicio no oficial:** si la Contraloría cambia el servicio, hay que ajustar `BuscadorCGRService`.
- **Solo informes que citan el CUI en su título.** Un informe de la obra que no lo cite no se encuentra. "Sin resultados" no significa que la obra no tenga informes.
- **Un error de tipeo del CUI en el título** de un informe hace que no se encuentre (se prefiere perder un informe antes que asociar uno equivocado).
- **Infobras** (infobras.contraloria.gob.pe) relaciona obra e informes con su propio sistema, con unos días de retraso (y, según lo observado en una sola obra, sin los informes de control posterior); en la comparación de dos obras (CUI 2650667 y 2518752) coincidió en todos los informes que lista, y BuscadorCGR tuvo uno más (control posterior). No se usa como fuente en esta versión.
- Las fechas de emisión pueden diferir entre BuscadorCGR e Infobras; la de publicación coincide.
- Los enlaces a PDF que entrega la Contraloría son `http` (no `https`).
- El significado exacto de `fecha_fin_ejecucion` no está documentado por la fuente (corresponde a la columna "Fecha Conclusión" del buscador).
- Pendiente del equipo: dónde se aloja la API y cómo la consume el backend principal.
