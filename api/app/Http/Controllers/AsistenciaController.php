<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAsistenciaRequest;
use App\Models\Alumno;
use App\Models\Clase;
use App\Services\Asistencia\RegistroDeClase;
use App\Services\Planes\ConsumidorDeClases;
use App\Services\Planes\ReglaDeAusencia;
use Illuminate\Http\JsonResponse;

/**
 * Asistencia de una clase.
 *
 * LO QUE ESTÁ PASANDO CON LA AUSENCIA
 * -----------------------------------
 * La respuesta de esta pantalla incluye SIEMPRE el estado de la regla de
 * consumo, porque quien está en la sala necesita saber si lo que acaba de marcar
 * descontó una clase del plan o quedó pendiente de decisión.
 *
 * No es un detalle de la interfaz: es la diferencia entre que el docente crea
 * que descontó y que la escuela vea que no descontó nada.
 */
class AsistenciaController extends Controller
{
    public function __construct(
        private readonly RegistroDeClase $registro,
        private readonly ReglaDeAusencia $regla,
        private readonly ConsumidorDeClases $consumidor,
    ) {}

    /** Lista de la clase, para la pantalla de toma de asistencia. */
    public function index(Clase $clase): JsonResponse
    {
        $this->authorize('ver', $clase);

        $filas = $clase->alumnos()->orderBy('alumnos.apellido')->orderBy('alumnos.nombre')->get();

        return response()->json([
            'clase' => [
                'id' => $clase->id,
                'nombre' => $clase->nombre,
                'inicio' => $clase->inicio?->toIso8601String(),
                'termina' => $clase->termina?->toIso8601String(),
            ],
            'alumnos' => $filas->map(fn (Alumno $alumno): array => [
                'id' => $alumno->id,
                'nombre_completo' => $alumno->nombreCompleto(),
                'cupo_restante' => $this->consumidor->cupoRestante($alumno),
            ])->all(),
            'regla_ausencia' => [
                'definida' => $this->regla->definida(),
                'explicacion' => $this->regla->explicacion(),
            ],
        ]);
    }

    /**
     * Marca la asistencia de un alumno.
     *
     * La ausencia genera una recuperación solo si el cuerpo lo pide. No se crea
     * automáticamente: puede ser que la escuela haya acordado con la familia
     * que ese día no se recupera, y esa es una decisión suya.
     */
    public function store(StoreAsistenciaRequest $peticion, Clase $clase, Alumno $alumno): JsonResponse
    {
        $this->authorize('ver', $clase);

        $resultado = $this->registro->registra(
            $alumno,
            $clase,
            [
                'estado' => \App\Enums\EstadoAsistencia::from($peticion->validated()['estado']),
                'generar_recuperacion' => (bool) $peticion->boolean('generar_recuperacion'),
            ],
            $peticion->user()?->id,
        );

        return response()->json([
            'mensaje' => 'Asistencia registrada.',
            'asistencia' => [
                'estado' => $resultado['asistencia']->estado?->value,
                // El tri-estado, tal como quedó. NULL es "la regla no está
                // definida", y hay que poder verlo.
                'consume_clase_plan' => $resultado['asistencia']->consume_clase_plan,
            ],
            'recuperacion' => $resultado['recuperacion'] === null ? null : [
                'id' => $resultado['recuperacion']->id,
                'vence_el' => $resultado['recuperacion']->vence_el?->toIso8601String(),
                'sin_caducidad' => $resultado['recuperacion']->esSinCaducidad(),
            ],
            'regla_ausencia' => [
                'definida' => $resultado['regla_ausencia_definida'],
                'explicacion' => $resultado['explicacion_regla'],
            ],
            'cupo_restante' => $this->consumidor->cupoRestante($alumno),
        ]);
    }
}