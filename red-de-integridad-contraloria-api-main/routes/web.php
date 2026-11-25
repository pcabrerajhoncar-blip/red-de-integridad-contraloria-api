<?php

use Illuminate\Support\Facades\Route;

// La raíz muestra información básica del servicio; no requiere interfaz gráfica.
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'servicio' => 'Red de Integridad - API de Contraloría',
        'estado' => 'activo',
        'endpoint' => '/api/contraloria/obras/{cui}/informes',
        'metodo' => 'GET',
    ]);
});
