<?php

declare(strict_types=1);

namespace App\Services\Planes;

use App\Enums\EstadoAsistencia;
use App\Enums\TipoMovimientoClasePlan;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Inscripcion;
use App\Models\MovimientoClasePlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consumo de clases del plan: el libro que responde "¿cuántas le quedan?".
 *
 * CUATRO CASOS, Y NINGUNO ES EL PENSADO
 * -------------------------------------
 *
 *  1. El alumno tiene plan y asiste: se consume una clase. Es el caso obvio.
 *
 *  2. El alumno tiene plan y falta:
 *     - Si la regla dice que consume, se consume.
 *     - Si la regla dice que NO consume, no se toca el cupo.
 *     - Si la regla NO está definida, NO se toca el cupo y la asistencia queda
 *       con `consume_clase_plan` en NULL. Esto es lo importante: el sistema no
 *       decide por la escuela.
 *
 *  3. El alumno NO tiene plan (paga clase por clase): no hay cupo que tocar y
 *     no se genera movimiento. El pago va por `pagos`, que es otro camino.
 *
 *  4. El plan no tiene `clases_incluidas` definido: se registra la asistencia,
 *     pero NO se genera movimiento. No se puede descontar de un cupo que
 *     nadie definió, y dejarlo en cero sería afirmar que el plan no incluye
 *     nada.
 */
class ConsumidorDeClases
{
    public function __construct(private readonly ReglaDeAusencia $regla) {}

    /**
     * Calcula la respuesta a "¿esta asistencia consume clase del plan?".
     *
     * @return bool|null TRUE consume, FALSE no consume, NULL sin definir.
     */
    public function decideConsumo(EstadoAsistencia $estado, bool $tienePlanUtilizable, bool $planTieneCupo): ?bool
    {
        // Sin plan o sin cupo definido no hay nada que consumir. No es lo mismo
        // que "definió que no consume": es que no hay de dónde descontar.
        if (! $tienePlanUtilizable || ! $planTieneCupo) {
            return false;
        }

        if ($estado === EstadoAsistencia::Presente) {
            return true;
        }

        // Aquí está la decisión del negocio. Si no está definida, se devuelve
        // NULL a propósito y la asistencia queda pendiente de revisar.
        return $this->regla->consume();
    }

    /**
     * Registra la asistencia y, si corresponde, el movimiento de consumo.
     *
     * Todo en una transacción: o quedan las dos filas, o ninguna. Si se
     * guardara la asistencia y fallara el movimiento, el cupo quedaría
     * desincronizado sin que nadie lo notara.
     */
    public function registrar(Alumno $alumno, string $claseId, EstadoAsistencia $estado, ?string $usuarioId = null): Asistencia
    {
        return DB::transaction(function () use ($alumno, $claseId, $estado, $usuarioId): Asistencia {
            $inscripcion = $this->inscripcionUtilizable($alumno);

            $plan = $inscripcion?->plan;
            $planTieneCupo = $plan !== null && $plan->tieneCupoDefinido();

            $consume = $this->decideConsumo($estado, $inscripcion !== null, $planTieneCupo);

            $asistencia = Asistencia::query()->updateOrCreate(
                [
                    'clase_id' => $claseId,
                    'alumno_id' => $alumno->id,
                ],
                [
                    'tenant_id' => $alumno->tenant_id,
                    'estado' => $estado,
                    // El valor queda congelado en esta fila: si mañana la
                    // escuela cambia la regla, esta asistencia sigue diciendo
                    // qué pasó con ella.
                    'consume_clase_plan' => $consume,
                    'registrado_por' => $usuarioId,
                    'registrado_en' => Carbon::now(),
                ]
            );

            if ($consume !== true || $inscripcion === null) {
                return $asistencia;
            }

            $this->anotarMovimiento(
                $alumno,
                $inscripcion,
                TipoMovimientoClasePlan::Consumo,
                $claseId,
                $asistencia,
                $estado === EstadoAsistencia::Presente
                    ? 'Clase assistida.'
                    : 'Ausencia que consume la clase según la regla configurada.',
            );

            return $asistencia;
        });
    }

    /**
     * Devuelve una clase al cupo.
     *
     * Se usa cuando una recuperación se usa (la escuela entregó la clase) o
     * cuando caduca y hay que corregir. Un `ajuste` sin motivo no se acepta:
     * la base lo rechaza (migración 2026_10_18_000015).
     */
    public function devolver(Alumno $alumno, string $motivo, ?string $claseId = null, ?string $asistenciaId = null): ?MovimientoClasePlan
    {
        $inscripcion = $this->inscripcionUtilizable($alumno);

        if ($inscripcion === null) {
            // Sin inscripción no hay plan al que devolverle nada. Se devuelve
            // NULL en vez de inventar un movimiento: la ausencia de resultado
            // es información.
            return null;
        }

        return $this->anotarMovimiento(
            $alumno,
            $inscripcion,
            TipoMovimientoClasePlan::Devolucion,
            $claseId,
            $asistenciaId !== null ? Asistencia::find($asistenciaId) : null,
            $motivo,
        );
    }

    /**
     * La inscripción viva del alumno, si tiene una.
     *
     * "Viva" significa activa y sin fecha de término. Una inscripción
     * suspendida no habilita consumo: si alguien pausa su mensualidad, no
     * debería verle gastar el cupo de un mes que no está pagando.
     */
    public function inscripcionUtilizable(Alumno $alumno): ?Inscripcion
    {
        return $alumno->inscripciones()
            ->where('estado', 'activa')
            ->whereNull('terminada_en')
            ->with('plan')
            ->orderByDesc('iniciada_en')
            ->first();
    }

    /**
     * Clases consumidas hasta la fecha indicada.
     *
     * Suma el libro, no un contador. `Ajuste` no suma: es documentación.
     */
    public function consumidas(Alumno $alumno, ?Carbon $hasta = null): int
    {
        $hasta ??= Carbon::now();

        return (int) $alumno->movimientosClasePlan()
            ->where('ocurre_en', '<=', $hasta)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'consumo' THEN -1 WHEN tipo = 'devolucion' THEN 1 ELSE 0 END), 0) AS saldo")
            ->value('saldo');
    }

    /**
     * Cupo restante.
     *
     * Cuando el plan no define `clases_incluidas`, devuelve NULL en vez de
     * cero. Cero significaría "no le queda nada", que es una afirmación que el
     * sistema no puede hacer.
     */
    public function cupoRestante(Alumno $alumno, ?Carbon $fecha = null): ?int
    {
        $inscripcion = $this->inscripcionUtilizable($alumno);

        if ($inscripcion === null || ! $inscripcion->plan->tieneCupoDefinido()) {
            return null;
        }

        return $inscripcion->plan->clases_incluidas - $this->consumidas($alumno, $fecha);
    }

    private function anotarMovimiento(
        Alumno $alumno,
        Inscripcion $inscripcion,
        TipoMovimientoClasePlan $tipo,
        ?string $claseId,
        ?Asistencia $asistencia,
        string $motivo
    ): MovimientoClasePlan {
        return MovimientoClasePlan::query()->create([
            'tenant_id' => $alumno->tenant_id,
            'alumno_id' => $alumno->id,
            'inscripcion_id' => $inscripcion->id,
            'clase_id' => $claseId,
            'asistencia_id' => $asistencia?->id,
            'tipo' => $tipo,
            'motivo' => $motivo,
            'ocurre_en' => Carbon::now(),
        ]);
    }
}