<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Alumno;
use App\Models\Plan;
use App\Models\Recuperacion;
use App\Services\Planes\ReglaDeAusencia;
use App\Services\Recuperacion\SaldoDeRecuperaciones;
use Illuminate\Http\JsonResponse;

/**
 * Tablero: lo que la escuela necesita ver al abrir la aplicación.
 *
 * NO ES UN RESUMEN DE ADORNOS
 * --------------------------
 * Muestra cuatro cosas y son cuatro por una razón:
 *
 *  1. Las clases de hoy, en orden. Es lo primero que se necesita.
 *  2. Las ausencias cuya regla quedó sin definir. Es trabajo pendiente que
 *     puede alterar el ingreso.
 *  3. La deuda de clases sin fecha de vencimiento. Sin esto, una deuda que no
 *     caduca es invisible.
 *  4. Los pagos pendientes, si quien entra tiene permiso para verlos.
 *
 * Si el rol no puede ver pagos, ese bloque no viaja en la respuesta. No viaja
 * oculto para que la interfaz lo esconda: no viaja.
 */
class TableroController extends Controller
{
    public function __invoke(
        ReglaDeAusencia $regla,
        SaldoDeRecuperaciones $saldo
    ): JsonResponse {
        $usuario = request()->user();

        $hoy = now()->startOfDay();

        $clasesHoy = \App\Models\Clase::query()
            ->where('inicio', '>=', $hoy)
            ->where('inicio', '<', $hoy->copy()->addDay())
            ->where('estado', '!=', 'anulada')
            ->with(['docente', 'sala'])
            ->orderBy('inicio')
            ->get();

        $ausenciasPorDefinir = Asistencia::query()
            ->whereNull('consume_clase_plan')
            ->where('estado', 'ausente')
            ->with('alumno')
            ->orderBy('registrado_en')
            ->limit(20)
            ->get();

        $deudaSinCaducidad = $saldo->sinCaducidad();

        $respuesta = [
            'clases_de_hoy' => $clasesHoy->map(fn ($clase): array => [
                'id' => $clase->id,
                'nombre' => $clase->nombre,
                'inicio' => $clase->inicio?->toIso8601String(),
                'termina' => $clase->termina?->toIso8601String(),
                'docente' => $clase->docente?->nombre_completo,
                'sala' => $clase->sala?->nombre,
            ])->all(),
            'regla_ausencia' => [
                'definida' => $regla->definida(),
                'consume' => $regla->consume(),
                'explicacion' => $regla->explicacion(),
            ],
            'ausencias_por_definir' => [
                'cantidad' => $ausenciasPorDefinir->count(),
                'ultimas' => $ausenciasPorDefinir->map(fn (Asistencia $a): array => [
                    'alumno' => $a->alumno?->nombreCompleto(),
                    'registrado_en' => $a->registrado_en->toIso8601String(),
                ])->all(),
            ],
            'deuda_sin_caducidad' => [
                'cantidad' => $deudaSinCaducidad->count(),
                'explicacion' => 'Estas clases recuperadas no tienen fecha de vencimiento. '
                    . 'La escuela no configuró ESCUELA_MUSICA_DIAS_RECUPERACION, '
                    . 'así que hay que revisarlas a mano.',
            ],
            'totales' => [
                'alumnos_activos' => Alumno::query()->where('activo', true)->count(),
                'planes_activos' => Plan::query()->where('activo', true)->count(),
                'recuperaciones_totales' => Recuperacion::query()
                    ->whereIn('estado', ['pendiente', 'agendada'])
                    ->count(),
            ],
        ];

        // El bloque de pagos solo se arma si el rol tiene permiso. Un docente
        // recibe la respuesta sin él, no con él en cero.
        if ($usuario?->vePagos()) {
            $respuesta['pagos_pendientes'] = \App\Models\Pago::query()
                ->where('anulado', false)
                ->whereNull('pagado_en')
                ->with('alumno')
                ->orderBy('periodo_hasta')
                ->limit(20)
                ->get()
                ->map(fn ($pago): array => [
                    'alumno' => $pago->alumno?->nombreCompleto(),
                    'monto' => $pago->monto,
                    'monto_definido' => $pago->monto !== null,
                    'periodo_hasta' => $pago->periodo_hasta?->toDateString(),
                ])->all();
        }

        return response()->json($respuesta);
    }
}