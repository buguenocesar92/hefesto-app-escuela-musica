<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Consola de artisan
|--------------------------------------------------------------------------
|
| Laravel descubre solo los comandos que están en `app/Console/Commands`, así
| que `escuela-musica:verificar-aislamiento`, `escuela-musica:crear`,
| `escuela-musica:revisar-morosidad` y `escuela-musica:vencer-recuperaciones`
| no se registran acá.
|
| Lo que sí vive acá es la programación de tareas.
|
| UNA TAREA POR ESCUELA, NO UNA GLOBAL
| -----------------------------------
| El trabajo de vencer recuperaciones necesita el contexto de escuela, porque la
| tabla `recuperaciones` tiene Row Level Security. Un `Schedule::job()` que
| corre sin contexto fallaría, y con razón: el aislamiento falla cerrado.
|
| Por eso se recorre la tabla `escuelas` y se encola un trabajo por escuela. La
| tarea global que hace el recorrido es `escuela-musica:vencer-recuperaciones
| --aplicar`, que es idempotente: correrla dos veces no rompe nada.
|
*/

Schedule::command('escuela-musica:vencer-recuperaciones --aplicar')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->description(
        'Vence las recuperaciones que pasaron su fecha y lista la deuda sin caducidad. '
        . 'Sin ESCUELA_MUSICA_DIAS_RECUPERACION no vence nada y solo informa.'
    );

/*
| Recorte de la bitácora de accesos.
|
| El valor por omisión de 8760 horas (un año) es generoso a propósito: lo que
| se borra de la bitácora no se recupera. La retención real la decide la
| escuela según su política de tratamiento de datos. Ver docs/07, sección "Lo
| que NO se puso".
*/
Schedule::command('model:prune', [
    '--model' => [App\Models\BitacoraAcceso::class],
    '--hours' => (int) config('escuela-musica.retencion_bitacora_horas', 8760),
])->dailyAt('03:00')
    ->description('Recorta la bitácora de accesos según la retención configurada.');