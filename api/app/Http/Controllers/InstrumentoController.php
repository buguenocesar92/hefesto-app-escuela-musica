<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EstadoDevolucion;
use App\Enums\EstadoInstrumento;
use App\Models\Alumno;
use App\Models\Instrumento;
use App\Models\PrestamoInstrumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Instrumentos: inventario, préstamo y devolución.
 *
 * UNA DEVOLUCIÓN CON DAÑOS NO DEJA EL INSTRUMENTO DISPONIBLE
 * -------------------------------------------------------
 * Si vuelve con una tecla hundida, el instrumento va a `mantencion`. Dejarlo
 * `disponible` significaría ofrecer a otro alumno algo que está roto.
 *
 * Y NO SE BORRA EL PRÉSTAMO
 * -------------------------
 * Un instrumento devuelto con daños deja su registro, con `estado_devolucion` en
 * `con_danos`. Ese registro es lo que sirve para reclamar, y borrarlo sería
 * perder justamente el dato útil.
 */
class InstrumentoController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('verListado', Instrumento::class);

        $consulta = Instrumento::query()
            ->withCount(['prestamos as prestamos_abiertos_count' => function ($q): void {
                $q->whereNull('devuelto_en');
            }]);

        if ($peticion->filled('estado')) {
            $consulta->where('estado', $peticion->input('estado'));
        }

        return response()->json([
            'instrumentos' => $consulta->orderBy('nombre')->get()->map(fn (Instrumento $i): array => [
                'id' => $i->id,
                'nombre' => $i->nombre,
                'tipo' => $i->tipo,
                'marca' => $i->marca,
                'codigo_interno' => $i->codigo_interno,
                'estado' => $i->estado?->value,
                'estado_texto' => $i->estado?->etiqueta(),
                'disponible' => $i->estaDisponible(),
                'prestamos_abiertos' => $i->prestamos_abiertos_count,
                'observacion' => $i->observacion,
            ])->all(),
        ]);
    }

    public function show(Instrumento $instrumento): JsonResponse
    {
        $this->authorize('ver', $instrumento);

        $prestamos = $instrumento->prestamos()->with('alumno')->orderByDesc('entregado_en')->get();

        return response()->json([
            'instrumento' => [
                'id' => $instrumento->id,
                'nombre' => $instrumento->nombre,
                'tipo' => $instrumento->tipo,
                'estado' => $instrumento->estado?->value,
                'estado_texto' => $instrumento->estado?->etiqueta(),
            ],
            'prestamos' => $prestamos->map(fn (PrestamoInstrumento $p): array => [
                'id' => $p->id,
                'alumno' => $p->alumno?->nombreCompleto(),
                'tipo' => $p->tipo,
                'entregado_en' => $p->entregado_en->toIso8601String(),
                'devuelto_en' => $p->devuelto_en?->toIso8601String(),
                'estado_devolucion' => $p->estado_devolucion?->value,
                'abierto' => $p->estaAbierto(),
            ])->all(),
        ]);
    }

    public function store(Request $peticion): JsonResponse
    {
        $this->authorize('crear', Instrumento::class);

        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'tipo' => ['nullable', 'string', 'max:80'],
            'marca' => ['nullable', 'string', 'max:80'],
            'codigo_interno' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $instrumento = Instrumento::query()->create($datos + [
            // El estado de partida siempre es disponible. Un instrumento nuevo
            // no puede empezar prestado.
            'estado' => EstadoInstrumento::Disponible->value,
        ]);

        return response()->json([
            'mensaje' => 'Instrumento registrado.',
            'instrumento' => ['id' => $instrumento->id, 'nombre' => $instrumento->nombre],
        ], 201);
    }

    /** Entrega un instrumento a un alumno. */
    public function prestar(Instrumento $instrumento, Request $peticion): JsonResponse
    {
        $this->authorize('prestar', $instrumento);

        $datos = $peticion->validate([
            'alumno_id' => ['required', 'uuid', 'exists:alumnos,id'],
            'tipo' => ['nullable', 'in:prestamo,arriendo'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $instrumento->estaDisponible()) {
            return response()->json([
                'mensaje' => 'El instrumento no está disponible para entregar.',
                'errores' => [
                    'instrumento' => ['Estado actual: ' . ($instrumento->estado?->etiqueta() ?? 'sin estado') . '.'],
                ],
            ], 422);
        }

        $prestamo = DB::transaction(function () use ($instrumento, $datos): PrestamoInstrumento {
            $prestamo = PrestamoInstrumento::query()->create([
                'instrumento_id' => $instrumento->id,
                'alumno_id' => $datos['alumno_id'],
                'tipo' => $datos['tipo'] ?? 'prestamo',
                'entregado_en' => now(),
                'observacion' => $datos['observacion'] ?? null,
            ]);

            $instrumento->estado = ($prestamo->tipo === 'arriendo'
                ? EstadoInstrumento::Arrendado
                : EstadoInstrumento::Prestado)->value;
            $instrumento->save();

            return $prestamo;
        });

        return response()->json([
            'mensaje' => 'Instrumento entregado.',
            'prestamo' => ['id' => $prestamo->id],
        ], 201);
    }

    /** Registra la devolución. */
    public function devolver(PrestamoInstrumento $prestamo, Request $peticion): JsonResponse
    {
        $this->authorize('devolver', $prestamo->instrumento);

        if (! $prestamo->estaAbierto()) {
            return response()->json([
                'mensaje' => 'Este préstamo ya fue devuelto.',
            ], 422);
        }

        $datos = $peticion->validate([
            'estado_devolucion' => ['required', 'in:bueno,con_danos,incompleto'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $estado = EstadoDevolucion::from($datos['estado_devolucion']);

        DB::transaction(function () use ($prestamo, $estado, $datos): void {
            $prestamo->devuelto_en = now();
            $prestamo->estado_devolucion = $estado;
            $prestamo->observacion = $datos['observacion'] ?? $prestamo->observacion;
            $prestamo->save();

            // Solo vuelve a `disponible` si volvió entero. Con daños o
            // incompleto, va a mantención.
            $prestamo->instrumento->estado = ($estado->dejaDisponible()
                ? EstadoInstrumento::Disponible
                : EstadoInstrumento::Mantencion)->value;
            $prestamo->instrumento->save();
        });

        return response()->json([
            'mensaje' => 'Devolución registrada.',
            'instrumento_estado' => $prestamo->instrumento->estado?->value,
        ]);
    }

    /** Préstamos de un alumno. */
    public function prestamosDeAlumno(Alumno $alumno): JsonResponse
    {
        $this->authorize('verPrestamosDeAlumno', $alumno);

        return response()->json([
            'prestamos' => $alumno->prestamosInstrumento()
                ->with('instrumento')
                ->orderByDesc('entregado_en')
                ->get()
                ->map(fn (PrestamoInstrumento $p): array => [
                    'id' => $p->id,
                    'instrumento' => $p->instrumento?->nombre,
                    'tipo' => $p->tipo,
                    'entregado_en' => $p->entregado_en->toIso8601String(),
                    'devuelto_en' => $p->devuelto_en?->toIso8601String(),
                    'estado_devolucion' => $p->estado_devolucion?->value,
                    'abierto' => $p->estaAbierto(),
                ])->all(),
        ]);
    }
}