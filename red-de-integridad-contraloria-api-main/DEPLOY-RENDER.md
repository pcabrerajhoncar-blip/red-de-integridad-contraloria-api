# Despliegue en Render

## Configuración del servicio

- New + → Web Service
- Conectar este repositorio y la rama principal (`main`, si corresponde).
- Language: Docker
- Dockerfile Path: `./Dockerfile`
- Instance Type: Free (para pruebas/demostración)
- Health Check Path: `/up`

No configurar Build Command ni Start Command: Render usará el Dockerfile.

## Variables de entorno

En Render → servicio → Environment, agregar:

| Key | Value |
|---|---|
| APP_NAME | Red Integridad API |
| APP_ENV | production |
| APP_DEBUG | false |
| APP_KEY | `base64:...` (generar una sola vez con `php artisan key:generate --show`) |
| APP_URL | `https://NOMBRE-DEL-SERVICIO.onrender.com` |
| LOG_LEVEL | info |
| CACHE_STORE | file |
| SESSION_DRIVER | file |
| QUEUE_CONNECTION | sync |
| CONTRALORIA_CACHE_TTL | 3600 |
| CONTRALORIA_MAX_PAGES | 3 |
| CONTRALORIA_TIMEOUT | 20 |

`APP_KEY`: ejecutar localmente `php artisan key:generate --show` y copiar el resultado completo. No compartirlo ni subirlo a GitHub. Mantener la misma clave entre despliegues.

No hace falta configurar una base de datos para el endpoint actual: los resultados se guardan temporalmente en caché de archivos. En el plan gratuito el sistema de archivos es efímero, por lo que la caché puede perderse al reiniciar o desplegar; esto no afecta a la consulta funcional.

## Endpoint

Después de un despliegue correcto:

`https://NOMBRE-DEL-SERVICIO.onrender.com/api/contraloria/obras/2650667/informes`

Prueba también el health check:

`https://NOMBRE-DEL-SERVICIO.onrender.com/up`

## Importante

- No subir el archivo `.env` ni secretos al repositorio.
- `APP_DEBUG` debe permanecer en `false`.
- La primera solicitud después de un periodo inactivo puede tardar porque el servicio gratuito puede entrar en suspensión.
- La API depende del servicio interno no oficial de BuscadorCGR; si la Contraloría cambia ese servicio, habrá que adaptar el cliente.
