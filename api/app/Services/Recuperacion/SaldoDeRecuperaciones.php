<?php

declare(strict_types=1);

namespace App\Services\Recuperacion;

use App\Enums\EstadoRecuperacion;
use App\Models\Alumno;
use App\Models\Recuperacion;
use Illuminate\Support\Carbon;

/**
 * Saldo de clases recuperadas pendientes.
 *
 * POR QUÉ ESTA CLASE EXISTE
 * ------------------------
 * Sin plazo de caducidad configurado, las recuperaciones se acumulan para
 * siempre. Una deuda que crece sin que nadie la mire deja de ser un problema
 * de los alumnos y pasa a ser un problema de la escuela: es dinero que la
 * escuela le debe y que ya nadie recuerda.
 *
 * Por eso el saldo se divide en dos números:
 *
 *  - `pendientes`: las que se pueden recuperar todavía.
 *  - `sinCaducidad`: las que NO tienen fecha de vencimiento. El día que la
 *    escuela configure el plazo, estas entran en la categoría anterior.
 *
 * Mostrar solo el total escondería justo lo que hay que revisar.
 */
class SaldoDeRecuperaciones
{
    public function __construct(private readonly CaducidadDeRecuperacion $caducidad) {}

    /**
     * Crea la recuperación de una ausencia.
     *
     * La fecha de vencimiento se calcula acá, con la regla configurada. Si no
     * hay regla, queda en NULL y la recuperación aparece en el saldo "sin
     * caducidad".
     */
    public function registrar(
        Alumno $alumno,
        string $claseOrigenId,
        ?string $asistenciaOrigenId = null,
        ?Carbon $en = null,
        ?string $motivo = null
    ): Recuperacion {
        $en ??= Carbon::now();

        return Recuperacion::query()->create([
            'tenant_id' => $alumno->tenant_id,
            'alumno_id' => $alumno->id,
            'clase_origen_id' => $claseOrigenId,
            'asistencia_origen_id' => $asistenciaOrigenId,
            'estado' => EstadoRecuperacion::Pendiente,
            'creada_en' => $en,
            'vence_el' => $this->caducidad->calculaVence($en),
            'motivo' => $motivo,
        ]);
    }

    /**
     * Resumen de la deuda de clases de un alumno.
     *
     * @return array{pendientes:int, sin_caducidad:int, con_vencimiento:int}
     */
    public function saldoDe(Alumno $alumno): array
    {
        $deudas = $alumno->recuperaciones()
            ->whereIn('estado', [
                EstadoRecuperacion::Pendiente->value,
                EstadoRecuperacion::Agendada->value,
            ]);

        $pendientes = 0;
        $sinCaducidad = 0;

        $deudas->get(['vence_el'])->each(function (Recuperacion $recuperacion) use (&$pendientes, &$sinCaducidad): void {
            $pendientes++;

            if ($recuperacion->vence_el === null) {
                $sinCaducidad++;
            }
        });

        return [
            'pendientes' => $pendientes,
            'sin_caducidad' => $sinCaducidad,
            'con_vencimiento' => $pendientes - $sinCaducidad,
        ];
    }

    /**
     * Deuda que la escuela ya no puede automatizar porque no tiene fecha.
     *
     * Es la lista que hay que mirar a mano en cada reunión de la escuela.
     */
    public function sinCaducidad(?Carbon $referencia = null)
    {
        $referencia ??= Carbon::now();

        return Recuperacion::query()
            ->whereIn('estado', [
                EstadoRecuperacion::Pendiente->value,
                EstadoRecuperacion::Agendada->value,
            ])
            ->whereNull('vence_el')
            ->where('creada_en', '<=', $referencia)
            ->with(['alumno', 'claseOrigen'])
            ->orderBy('creada_en')
            ->get();
    }

    /**
     * Deuda que ya pasó su fecha y sigue pendiente.
     *
     * Con la regla configurada, el comando `escuela-musica:vencer-recuperaciones`
     * la marca como vencida y devuelve la clase al cupo.
     */
    public function vencidas(?Carbon $referencia = null)
    {
        $referencia ??= Carbon::now();

        return Recuperacion::query()
            ->whereIn('estado', [
                EstadoRecuperacion::Pendiente->value,
                EstadoRecuperacion::Agendada->value,
            ])
            ->whereNotNull('vence_el')
            ->where('vence_el', '<', $referencia)
            ->with(['alumno', 'claseOrigen'])
            ->orderBy('vence_el')
            ->get();
    }
}