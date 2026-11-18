<?php

use App\Http\Controllers\ContraloriaController;
use Illuminate\Support\Facades\Route;

// Laravel agrega el prefijo /api automáticamente.
// Máximo 30 consultas por minuto por IP, para no sobrecargar a la Contraloría.
Route::get('/contraloria/obras/{cui}/informes', [ContraloriaController::class, 'informes'])
    ->middleware('throttle:30,1')
    ->name('contraloria.informes');
