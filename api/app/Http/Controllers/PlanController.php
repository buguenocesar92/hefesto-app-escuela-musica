<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Planes mensuales.
 *
 * LO QUE SE MUESTRA CUANDO NO HAY PRECIO
 * -------------------------------------
 * Un plan sin precio definido muestra `precio: null` y
 * `precio_definido: false`. No muestra 0 ni un precio de ejemplo. Un precio
 * inventado en esta pantalla termina en una factura.
 */
class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('verListado', Plan::class);

        return response()->json([
            'planes' => Plan::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'nombre' => $plan->nombre,
                    'descripcion' => $plan->descripcion,
                    'clases_incluidas' => $plan->clases_incluidas,
                    'cupo_definido' => $plan->tieneCupoDefinido(),
                    'precio' => $plan->precio,
                    'precio_definido' => $plan->tienePrecioDefinido(),
                    'vigencia_dias' => $plan->vigencia_dias,
                    'activo' => $plan->activo,
                ])->all(),
        ]);
    }

    public function show(Plan $plan): JsonResponse
    {
        $this->authorize('ver', $plan);

        return response()->json([
            'plan' => [
                'id' => $plan->id,
                'nombre' => $plan->nombre,
                'descripcion' => $plan->descripcion,
                'clases_incluidas' => $plan->clases_incluidas,
                'precio' => $plan->precio,
                'precio_definido' => $plan->tienePrecioDefinido(),
                'vigencia_dias' => $plan->vigencia_dias,
                'activo' => $plan->activo,
                'inscripciones_activas' => $plan->inscripcionesActivas()->count(),
            ],
        ]);
    }

    public function store(Request $peticion): JsonResponse
    {
        $this->authorize('crear', Plan::class);

        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            // Los tres aceptan NULL y eso es intencional: son decisiones de la
            // escuela, no valores que el sistema deba deducir.
            'clases_incluidas' => ['nullable', 'integer', 'min:0'],
            'precio' => ['nullable', 'integer', 'min:0'],
            'vigencia_dias' => ['nullable', 'integer', 'min:1'],
        ]);

        $plan = Plan::query()->create($datos + ['activo' => true]);

        return response()->json([
            'mensaje' => 'Plan creado.',
            'plan' => ['id' => $plan->id, 'nombre' => $plan->nombre],
        ], 201);
    }

    public function update(Request $peticion, Plan $plan): JsonResponse
    {
        $this->authorize('actualizar', $plan);

        $datos = $peticion->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'clases_incluidas' => ['nullable', 'integer', 'min:0'],
            'precio' => ['nullable', 'integer', 'min:0'],
            'vigencia_dias' => ['nullable', 'integer', 'min:1'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $plan->update($datos);

        return response()->json([
            'mensaje' => 'Plan actualizado.',
            'plan' => ['id' => $plan->id],
        ]);
    }
}