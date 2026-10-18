<?php

declare(strict_types=1);

namespace App\Services\Agenda;

use App\Models\Clase;
use Illuminate\Support\Carbon;

/**
 * Detección de choque de horario en la agenda.
 *
 * EL PROBLEMA OPERATIVO DEL DÍA
 * -----------------------------
 * Cada clase necesita un docente y una sala. Lo que se rompe todos los días no
 * es la lista de nombres: es que la misma persona, o el mismo espacio, esté en
 * dos lugares a la vez.
 *
 * POR QUÉ NO LO IMPONE LA BASE
 * ----------------------------
 * "Estos intervalos de tiempo se pisan" no se expresa en un índice normal de
 * PostgreSQL. Se podría con una restricción de exclusión sobre un rango
 * (`tstzrange`) con índice GiST, y es la solución que se debería usar si el
 * volumen de clases lo justificara. No se puso porque:
 *
 *  - exigiría la extensión `btree_gist`, que hay que habilitar en la base;
 *  - y, sobre todo, no se pudo comprobar que la extensión esté disponible en el
 *    entorno de instalación.
 *
 * Se prefiere una comprobación en la aplicación, escrita y visible, antes que
 * una restricción que no se pudo verificar. Queda anotado en
 * docs/10-PRUEBAS-Y-ESTADO.md, sección "Lo que quedó a medias".
 *
 * La comparación de intervalos es CERRADA por el inicio y ABIERTA por el
 * término: una clase de 18:00 a 19:00 y otra de 19:00 a 20:00 no se pisan,
 * porque la segunda empieza exactamente cuando termina la primera.
 */
class DetectorDeSolapamiento
{
    /**
     * Clases que chocan con el horario propuesto.
     *
     * @param  string|null  $ignorarClaseId  Clase que se está editando: no
     *                                      cuenta como choque consigo misma.
     * @return list<Clase>
     */
    public function choques(
        string $docenteId,
        string $salaId,
        Carbon $inicio,
        Carbon $termina,
        ?string $ignorarClaseId = null
    ): array {
        $consulta = Clase::query()
            ->where('estado', '!=', 'anulada')
            ->where('inicio', '<', $termina)
            ->where('termina', '>', $inicio)
            // Choca si el docente se repite O si la sala se repite. Con las
            // dos condiciones juntas en O, un choque por cualquiera de los dos
            // motivos aparece en el resultado.
            ->where(function ($sub) use ($docenteId, $salaId): void {
                $sub->where('docente_id', $docenteId)
                    ->orWhere('sala_id', $salaId);
            })
            ->orderBy('inicio');

        if ($ignorarClaseId !== null) {
            $consulta->where('id', '!=', $ignorarClaseId);
        }

        return $consulta->get()->all();
    }

    /**
     * ¿Hay algún choque?
     */
    public function hayChoque(
        string $docenteId,
        string $salaId,
        Carbon $inicio,
        Carbon $termina,
        ?string $ignorarClaseId = null
    ): bool {
        return $this->choques($docenteId, $salaId, $inicio, $termina, $ignorarClaseId) !== [];
    }

    /**
     * Texto para la interfaz, separando el motivo.
     *
     * La escuela necesita saber si el problema es de persona o de sala: son
     * dos arreglos distintos y el mensaje tiene que decir cuál de los dos es.
     *
     * @param  list<Clase>  $choques
     */
    public function mensaje(array $choques, string $docenteId, string $salaId): string
    {
        $motivoDocente = false;
        $motivoSala = false;

        foreach ($choques as $clase) {
            if ($clase->docente_id === $docenteId) {
                $motivoDocente = true;
            }

            if ($clase->sala_id === $salaId) {
                $motivoSala = true;
            }
        }

        $partes = [];

        if ($motivoDocente) {
            $partes[] = 'El docente ya tiene una clase en ese horario.';
        }

        if ($motivoSala) {
            $partes[] = 'La sala ya está ocupada en ese horario.';
        }

        if ($partes === []) {
            // No debería pasar: solo se llama cuando hay choques. Se deja el
            // texto genérico igual, para que nunca quede un mensaje vacío.
            return 'El horario choca con otra clase de la agenda.';
        }

        return implode(' ', $partes) . ' Revise la agenda antes de guardar.';
    }

    /**
     * ¿El intervalo es inválido?
     *
     * Va aparte del solapamiento: una clase que termina antes de empezar no
     * choca con nada, simplemente no tiene sentido.
     */
    public function tieneIntervaloInvalido(Carbon $inicio, Carbon $termina): bool
    {
        return $termina->lessThanOrEqualTo($inicio);
    }
}