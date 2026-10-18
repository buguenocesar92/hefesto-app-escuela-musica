<?php

declare(strict_types=1);

use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClaseController;
use App\Http\Controllers\InstrumentoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RecuperacionController;
use App\Http\Controllers\TableroController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| Todo lo de negocio vive bajo /api/v1 y exige sesión. Las únicas rutas
| abiertas son las de autenticación y la de salud, que es la que usa el
| sondeo de salud del contenedor.
|
| El middleware `ResolverTenant` no se lista ruta por ruta: se aplica a todo el
| grupo `web` en bootstrap/app.php, ANTES que `auth`. Si se pusiera después,
| las políticas de Row Level Security no tendrían contexto y todo fallaría.
|
*/

// --- Rutas abiertas ------------------------------------------------------

// El selector de escuela del ingreso. Lee `vw_escuelas_publicas`.
// Es la única lectura de la tabla `escuelas` sin contexto, y por eso va por la
// vista de tres columnas.
Route::prefix('api/v1/auth')->name('auth.')->group(function (): void {
    Route::get('/escuelas', [AuthController::class, 'escuelas'])->name('escuelas');
    Route::get('/login', [AuthController::class, 'entrar'])->name('entrar');
    Route::get('/callback', [AuthController::class, 'callback'])->name('callback');
    Route::post('/logout', [AuthController::class, 'salir'])->name('salir');
});

// --- Sesión -------------------------------------------------------------

Route::get('/api/v1/auth/usuario', [AuthController::class, 'yo'])
    ->middleware('auth')
    ->name('auth.usuario');

// --- Rutas con sesión ---------------------------------------------------

Route::middleware('auth')->prefix('api/v1')->name('api.')->group(function (): void {

    Route::get('/tablero', TableroController::class)->name('tablero');

    // Alumnos
    Route::get('/alumnos', [AlumnoController::class, 'index'])->name('alumnos.index');
    Route::post('/alumnos', [AlumnoController::class, 'store'])->name('alumnos.store');
    Route::get('/alumnos/{alumno}', [AlumnoController::class, 'show'])->name('alumnos.show');
    Route::put('/alumnos/{alumno}', [AlumnoController::class, 'update'])->name('alumnos.update');

    // Planes
    Route::get('/planes', [PlanController::class, 'index'])->name('planes.index');
    Route::post('/planes', [PlanController::class, 'store'])->name('planes.store');
    Route::get('/planes/{plan}', [PlanController::class, 'show'])->name('planes.show');
    Route::put('/planes/{plan}', [PlanController::class, 'update'])->name('planes.update');

    // Clases y agenda
    Route::get('/clases', [ClaseController::class, 'index'])->name('clases.index');
    Route::post('/clases', [ClaseController::class, 'store'])->name('clases.store');
    Route::get('/clases/{clase}', [ClaseController::class, 'show'])->name('clases.show');
    Route::put('/clases/{clase}', [ClaseController::class, 'update'])->name('clases.update');

    // Asistencia
    Route::get('/clases/{clase}/asistencias', [AsistenciaController::class, 'index'])
        ->name('asistencias.index');
    Route::post('/clases/{clase}/alumnos/{alumno}/asistencia', [AsistenciaController::class, 'store'])
        ->name('asistencias.store');

    // Pagos
    Route::get('/pagos', [PagoController::class, 'index'])->name('pagos.index');
    Route::post('/pagos', [PagoController::class, 'store'])->name('pagos.store');
    Route::get('/alumnos/{alumno}/pagos', [PagoController::class, 'deAlumno'])->name('pagos.alumno');
    Route::post('/pagos/{pago}/pagado', [PagoController::class, 'marcarPagado'])->name('pagos.pagado');
    Route::post('/pagos/{pago}/anular', [PagoController::class, 'anular'])->name('pagos.anular');

    // Recuperaciones
    Route::get('/recuperaciones', [RecuperacionController::class, 'index'])->name('recuperaciones.index');
    Route::get('/recuperaciones/resumen', [RecuperacionController::class, 'resumen'])
        ->name('recuperaciones.resumen');
    Route::get('/alumnos/{alumno}/recuperaciones', [RecuperacionController::class, 'deAlumno'])
        ->name('recuperaciones.alumno');

    // Instrumentos
    Route::get('/instrumentos', [InstrumentoController::class, 'index'])->name('instrumentos.index');
    Route::post('/instrumentos', [InstrumentoController::class, 'store'])->name('instrumentos.store');
    Route::get('/instrumentos/{instrumento}', [InstrumentoController::class, 'show'])
        ->name('instrumentos.show');
    Route::post('/instrumentos/{instrumento}/prestar', [InstrumentoController::class, 'prestar'])
        ->name('instrumentos.prestar');
    Route::post('/prestamos/{prestamo}/devolver', [InstrumentoController::class, 'devolver'])
        ->whereUuid('prestamo')
        ->name('prestamos.devolver');
    Route::get('/alumnos/{alumno}/prestamos', [InstrumentoController::class, 'prestamosDeAlumno'])
        ->name('prestamos.alumno');
});