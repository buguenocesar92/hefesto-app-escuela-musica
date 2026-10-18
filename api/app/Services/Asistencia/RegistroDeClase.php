<?php

declare(strict_types=1);

namespace App\Services\Asistencia;

use App\Enums\EstadoAsistencia;
use App\Enums\EstadoRecuperacion;
use App\Models\Alumno;
use App\Models\Clase;
use App\Models\Recuperacion;
use App\Services\Planes\ConsumidorDeClases;
use App\Services\Planes\ReglaDeAusencia;
use App\Services\Recuperacion\SaldoDeRecuperaciones;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Registro de una clase completa: quién viene, quién falta y qué pasa con las
 * recuperaciones.
 *
 * POR QUÉ UNA CLASE Y NO UNA ASISTENCIA POR VEZ
 * ---------------------------------------------
 * El docente está en la sala con veinte personas. El sistema tiene que permitir
 * mandar la lista entera en un solo llamado y que cada fila se arme sola: el
 * nombre, si vino, y si esa ausencia dejó una recuperación.
 *
 * CUANDO SE REGISTRA UNA AUSENCIA
 * ------------------------------
 * Tres cosas pueden pasar, y el sistema no elige entre ellas:
 *
 *  1. Se registra la asistencia con el valor de la regla tal como está.
 *  2. Si hubo ausencia, se crea la recuperación con su fecha de vencimiento
 *     calculada, o sin fecha si no hay caducidad configurada.
 *  3. Si la regla de consumo NO está definida, todo lo demás sigue igual y el
 *     cupo del plan no se toca. La ausencia queda marcada como pendiente y
 *     aparece en el tablero.
 */
class RegistroDeClase
{
    public function __construct(
        private readonly ConsumidorDeClases $consumidor,
        private readonly SaldoDeRecuperaciones $saldo,
        private readonly ReglaDeAusencia $regla,
    ) {}

    /**
     * Registra la asistencia de un alumno a una clase.
     *
     * @param  array{
     *     estado: EstadoAsistencia,
     *     generar_recuperacion: bool
     * }  $datos
     * @return array{
     *     asistencia: \App\Models\Asistencia,
     *     recuperacion: Recuperacion|null,
     *     regla_ausencia_definida: bool,
     *     explicacion_regla: string
     * }
     */
    public function registra(Alumno $alumno, Clase $clase, array $datos, ?string $usuarioId = null): array
    {
        return DB::transaction(function () use ($alumno, $clase, $datos, $usuarioId): array {
            $asistencia = $this->consumidor->registar(
                $alumno,
                $clase->id,
                $datos['estado'],
                $usuarioId
            );

            $recuperacion = null;

            if ($datos['estado'] === EstadoAsistencia::Ausente && $datos['generar_recuperacion']) {
                $recuperacion = $this->saldo->registrar(
                    $alumno,
                    $clase->id,
                    $asistencia->id,
                );
            }

            return [
                'asistencia' => $asistencia,
                'recuperacion' => $recuperacion,
                'explicacion_regla' => $this->regla->explicacion(),
                'regla_ausencia_definida' => $this->regla->definida(),
            ];
        });
    }

    /**
     * Cancela una recuperación: la escuela decide que esa deuda no era real.
     *
     * No borra nada. Deja la fila con estado `anulada`, que es la diferencia
     * entre "no pasó" y "se corrigió".
     *
     * @throws RuntimeException si la recuperación ya se usó.
     */
    public function anulaRecuperacion(Recuperacion $recuperacion, string $motivo): Recuperacion
    {
        if ($recuperacion->estado === EstadoRecuperacion::Usada) {
            throw new RuntimeException('Una recuperación ya usada no se puede anular.');
        }

        $recuperacion->estado = EstadoRecuperacion::Anulada;
        $recuperacion->motivo = $motivo;
        $recuperacion->save();

        return $recuperacion;
    }

    /**
     * Marca una recuperación como usada.
     *
     * Se llama cuando el alumno efectivamente va a la clase de reposición.
     */
    public function usaRecuperacion(Recuperacion $recuperacion, Clase $claseNueva): Recuperacion
    {
        $recuperacion->estado = EstadoRecuperacion::Usada;
        $recuperacion->clase_nueva_id = $claseNueva->id;
        $recuperacion->usada_en = now();
        $recuperacion->save();

        return $recuperacion;
    }
}