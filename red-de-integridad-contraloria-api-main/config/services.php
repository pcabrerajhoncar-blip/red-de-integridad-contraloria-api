<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'contraloria' => [
        // Servicio interno que usa la página del BuscadorCGR (no es una API oficial documentada).
        'url' => env('CONTRALORIA_URL', 'https://appbp.contraloria.gob.pe/BuscadorCGR/Informes/BusquedaInformesCGR.ashx'),
        'referer' => env('CONTRALORIA_REFERER', 'https://appbp.contraloria.gob.pe/BuscadorCGR/Informes/Avanzado.html'),
        'user_agent' => env('CONTRALORIA_USER_AGENT', 'RedIntegridadLambayeque-UniversidadSipan/1.0 (proyecto universitario)'),

        // Segundos de espera máxima por cada consulta a la Contraloría.
        'timeout' => (int) env('CONTRALORIA_TIMEOUT', 20),

        // Segundos que se guarda en caché el resultado de un CUI (0 = sin caché).
        'cache_ttl' => (int) env('CONTRALORIA_CACHE_TTL', 3600),

        // Informes por página (la Contraloría aceptó 50) y máximo de páginas por consulta.
        'page_size' => 50,
        'max_pages' => (int) env('CONTRALORIA_MAX_PAGES', 3),

        // Cantidad de dígitos aceptada para el CUI de entrada.
        'cui_min_digitos' => 6,
        'cui_max_digitos' => 7,
    ],

];
