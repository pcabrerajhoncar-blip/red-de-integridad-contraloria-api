<?php

use Illuminate\Support\Facades\Route;

// Pantalla de prueba de la API de Contraloría.
Route::get('/', function () {
    return view('contraloria.consulta');
});
