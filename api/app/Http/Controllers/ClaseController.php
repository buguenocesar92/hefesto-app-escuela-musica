<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreClaseRequest;
use App\Models\Clase;
use App\Services\Agenda\DetectorDeSolapamiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Clases y agenda.
 *
 * EL CHOQUE DE HORARIO SE COMPRUEBA ANTES DE GUARDAR
 * --------------------------------------------------
 * Se devuelve 422 con el motivo del choque, separando "choca el docente" de
 * "choca la sala". La escuela necesita saber cuál de las dos cosas tiene que
 * mover: son arreglos distintos.
 */
class ClaseController extends Controller
{
    public function __construct(private readonly DetectorDeSolapamiento $detector) {}

    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('verListado', Clase::class);

        $consulta = Clase::query()->with(['docente', 'sala'])->orderBy('inicio');

        if ($peticion->filled('desde')) {
            $consulta->where('termina', '>=', Carbon::parse((string) $peticion->input('desde')));
        }

        if ($peticion->filled('hasta')) {
            $consulta->where('inicio', '<=', Carbon::parse((string) $peticion->input('hasta')));
        }

        if ($peticion->filled('docente_id')) {
            $consulta->where('docente_id', $peticion->input('docente_id'));
        }

        return response()->json([
            'clases' => $consulta->get()->map(fn (Clase $clase): array => $this->fila($clase))->all(),
        ]);
    }

    public function show(Clase $clase): JsonResponse
    {
        $this->authorize('ver', $clase);

        return response()->json([
            'clase' => array_merge($this->fila($clase), [
                'notas' => $clase->notas,
                'alumnos' => $clase->alumnos()->orderBy('apellido')->get()
                    ->map(fn ($alumno): array => [
                        'id' => $alumno->id,
                        'nombre_completo' => $alumno->nombreCompleto(),
                    ])->all(),
            ]),
        ]);
    }

    public function store(StoreClaseRequest $peticion): JsonResponse
    {
        $datos = $peticion->validated();

        $inicio = Carbon::parse($datos['inicio']);
        $termina = Carbon::parse($datos['termina']);

        $this->rechazaSiChoca($datos['docente_id'], $datos['sala_id'], $inicio, $termina, null);

        $clase = Clase::query()->create([
            'nombre' => $datos['nombre'],
            'docente_id' => $datos['docente_id'],
            'sala_id' => $datos['sala_id'],
            'inicio' => $inicio,
            'termina' => $termina,
            'capacidad' => $datos['capacidad'] ?? null,
            'estado' => 'agendada',
            'notas' => $datos['notas'] ?? null,
        ]);

        return response()->json([
            'mensaje' => 'Clase agendada.',
            'clase' => $this->fila($clase->fresh()),
        ], 201);
    }

    public function update(StoreClaseRequest $peticion, Clase $clase): JsonResponse
    {
        $this->authorize('actualizar', $clase);

        $datos = $peticion->validated();

        $inicio = Carbon::parse($datos['inicio']);
        $termina = Carbon::parse($datos['termina']);

        // Se ignora la propia clase: si no, no se podría mover una clase a otra
        // hora porque siempre "chocaría" consigo misma.
        $this->rechazaSiChoca($datos['docente_id'], $datos['sala_id'], $inicio, $termina, $clase->id);

        $clase->update($datos + ['estado' => $clase->estado]);

        return response()->json([
            'mensaje' => 'Clase actualizada.',
            'clase' => $this->fila($clase->fresh()),
        ]);
    }

    /**
     * Rechaza con 422 si el horario choca.
     *
     * @throws ValidationException
     */
    private function rechazaSiChoca(
        string $docenteId,
        string $salaId,
        Carbon $inicio,
        Carbon $termina,
        ?string $ignorarClaseId
    ): void {
        if ($this->detector->tieneIntervaloInvalido($inicio, $termina)) {
            throw ValidationException::withMessages([
                'termina' => 'La hora de término debe ser posterior a la de inicio.',
            ]);
        }

        $choques = $this->detector->choques($docenteId, $salaId, $inicio, $termina, $ignorarClaseId);

        if ($choques === []) {
            return;
        }

        throw ValidationException::withMessages([
            'inicio' => [
                $this->detector->mensaje($choques, $docenteId, $salaId),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Clase $clase): array
    {
        return [
            'id' => $clase->id,
            'nombre' => $clase->nombre,
            'inicio' => $clase->inicio?->toIso8601String(),
            'termina' => $clase->termina?->toIso8601String(),
            'estado' => $clase->estado,
            'capacidad' => $clase->capacidad,
            'docente' => $clase->docente === null ? null : [
                'id' => $clase->docente->id,
                'nombre_completo' => $clase->docente->nombre_completo,
            ],
            'sala' => $clase->sala === null ? null : [
                'id' => $clase->sala->id,
                'nombre' => $clase->sala->nombre,
            ],
        ];
    }
}