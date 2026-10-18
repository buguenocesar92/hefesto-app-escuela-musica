<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Recuperacion;
use App\Services\Planes\ConsumidorDeClases;
use App\Services\Recuperacion\SaldoDeRecuperaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deuda de clases recuperadas.
 *
 * POR QUÉ ESTA PANTALLA EXISTE CUANDO NO HAY CADUCIDAD
 * ---------------------------------------------------
 * Si la escuela no configuró `ESCUELA_MUSICA_DIAS_RECUPERACION`, las clases
 * recuperadas no caducan nunca. La deuda crece y, si esta pantalla no la
 * muestra, nadie la vería.
 *
 * Por eso la respuesta separa las que tienen fecha de las que no la tienen. Un
 * total único escondería justo lo que hay que revisar.
 */
class RecuperacionController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $consulta = Recuperacion::query()->with(['alumno', 'claseOrigen']);

        if ($peticion->filled('alumno_id')) {
            $consulta->where('alumno_id', $peticion->input('alumno_id'));
        }

        if ($peticion->filled('estado')) {
            $consulta->where('estado', $peticion->input('estado'));
        }

        $deudas = $consulta->orderBy('creada_en')->get();

        $sinCaducidad = $deudas->filter(
            fn (Recuperacion $r): bool => $r->esSinCaducidad()
        )->count();

        return response()->json([
            'recuperaciones' => $deudas->map(fn (Recuperacion $r): array => [
                'id' => $r->id,
                'alumno' => $r->alumno?->nombreCompleto(),
                'clase_origen' => $r->claseOrigen?->nombre,
                'estado' => $r->estado?->value,
                'creada_en' => $r->creada_en->toIso8601String(),
                'vence_el' => $r->vence_el?->toIso8601String(),
                // La bandera que hay que mirar: no tiene fecha, hay que
                // revisarla a mano.
                'sin_caducidad' => $r->esSinCaducidad(),
            ])->all(),
            'resumen' => [
                'total' => $deudas->count(),
                'sin_caducidad' => $sinCaducidad,
            ],
        ]);
    }

    /** Deuda de un alumno. */
    public function deAlumno(Alumno $alumno): JsonResponse
    {
        $this->authorize('ver', $alumno);

        $saldo = app(SaldoDeRecuperaciones::class);
        $consumidor = app(ConsumidorDeClases::class);

        return response()->json([
            'alumno' => [
                'id' => $alumno->id,
                'nombre_completo' => $alumno->nombreCompleto(),
            ],
            'saldo' => $saldo->saldoDe($alumno),
            'plan' => [
                'cupo_restante' => $consumidor->cupoRestante($alumno),
            ],
        ]);
    }

    /** Deuda de toda la escuela, agrupada por cantidad pendiente. */
    public function resumen(): JsonResponse
    {
        $saldo = app(SaldoDeRecuperaciones::class);

        $deudas = Recuperacion::query()
            ->whereIn('estado', ['pendiente', 'agendada'])
            ->get(['alumno_id', 'vence_el']);

        $porAlumno = [];

        foreach ($deudas as $deuda) {
            $clave = $deuda->alumno_id;

            if (! isset($porAlumno[$clave])) {
                $porAlumno[$clave] = ['pendientes' => 0, 'sin_caducidad' => 0];
            }

            $porAlumno[$clave]['pendientes']++;

            if ($deuda->vence_el === null) {
                $porAlumno[$clave]['sin_caducidad']++;
            }
        }

        $totalSinCaducidad = array_sum(array_column($porAlumno, 'sin_caducidad'));

        return response()->json([
            'total_pendientes' => $deudas->count(),
            'total_sin_caducidad' => $totalSinCaducidad,
            'por_alumno' => $porAlumno,
            // El texto va en la respuesta para que la interfaz no tenga que
            // inventar la explicación.
            'explicacion' => $totalSinCaducidad > 0
                ? 'Hay clases recuperadas sin fecha de vencimiento porque la escuela no configuró '
                    . 'ESCUELA_MUSICA_DIAS_RECUPERACION. Esas no caducan solas: hay que revisarlas.'
                : 'Todas las clases recuperadas pendientes tienen fecha de vencimiento.',
        ]);
    }
}