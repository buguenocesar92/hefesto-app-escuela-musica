<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ConceptoPago;
use App\Http\Requests\StorePagoRequest;
use App\Models\Alumno;
use App\Models\Pago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pagos y estado de cuenta.
 *
 * UN SOLO ESTADO DE CUENTA
 * ------------------------
 * La cuota del plan y la clase suelta salen de la misma tabla, así que el saldo
 * del alumno se arma de una sola consulta. Ver la nota de la migración
 * 2026_10_18_000010.
 *
 * LO QUE EL SALDO DICE Y LO QUE NO
 * --------------------------------
 * `saldo_pendiente` suma solamente los pagos con monto DEFINIDO. Si el plan no
 * tiene precio, el cargo aparece en la lista pero no suma al saldo, porque no se
 * sabe cuánto vale. Es preferible mostrar "sin monto definido" que mostrar un 0
 * que parece que no debe nada.
 */
class PagoController extends Controller
{
    /** Estado de cuenta de un alumno. */
    public function deAlumno(Alumno $alumno): JsonResponse
    {
        $this->authorize('ver', $alumno);

        $pagos = $alumno->pagos()
            ->where('anulado', false)
            ->orderByDesc('periodo_hasta')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'alumno' => [
                'id' => $alumno->id,
                'nombre_completo' => $alumno->nombreCompleto(),
            ],
            'pagos' => $pagos->map(fn (Pago $pago): array => [
                'id' => $pago->id,
                'concepto' => $pago->concepto?->value,
                'concepto_texto' => $pago->concepto?->etiqueta(),
                // NULL se muestra explícito, no como 0.
                'monto' => $pago->monto,
                'monto_definido' => $pago->monto !== null,
                'periodo_desde' => $pago->periodo_desde?->toDateString(),
                'periodo_hasta' => $pago->periodo_hasta?->toDateString(),
                'pagado_en' => $pago->pagado_en?->toIso8601String(),
                'pendiente' => $pago->estaPendiente(),
                'metodo' => $pago->metodo,
                'referencia' => $pago->referencia,
            ])->all(),
            'saldo_pendiente' => $this->saldoPendiente($pagos->all()),
            'tiene_montos_sin_definir' => $pagos->contains(fn (Pago $pago): bool => $pago->monto === null),
        ]);
    }

    /** Listado de pagos de la escuela. */
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('verListado', Pago::class);

        $consulta = Pago::query()->with('alumno')->orderByDesc('created_at');

        if ($peticion->boolean('solo_pendientes')) {
            $consulta->where('anulado', false)->whereNull('pagado_en');
        }

        $porPagina = max(1, min(
            (int) $peticion->input('por_pagina', 50),
            (int) config('escuela-musica.por_pagina_maximo', 100)
        ));

        $pagina = $consulta->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())->map(fn (Pago $pago): array => [
                'id' => $pago->id,
                'alumno' => $pago->alumno?->nombreCompleto(),
                'concepto' => $pago->concepto?->value,
                'monto' => $pago->monto,
                'periodo_hasta' => $pago->periodo_hasta?->toDateString(),
                'pagado_en' => $pago->pagado_en?->toIso8601String(),
            ])->all(),
            'meta' => [
                'pagina' => $pagina->currentPage(),
                'paginas' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function store(StorePagoRequest $peticion): JsonResponse
    {
        $datos = $peticion->validated();
        $concepto = ConceptoPago::from($datos['concepto']);

        // Una clase suelta no viene de un plan: mandarle una inscripción es
        // un dato contradictorio y se rechaza.
        if (! $concepto->admiteInscripcion() && ($datos['inscripcion_id'] ?? null) !== null) {
            return response()->json([
                'mensaje' => 'Un pago por clase suelta no puede llevar una inscripción asociada.',
                'errores' => [
                    'inscripcion_id' => ['Quítela: el pago es por clase suelta.'],
                ],
            ], 422);
        }

        $pago = Pago::query()->create($datos);

        return response()->json([
            'mensaje' => 'Pago registrado.',
            'pago' => [
                'id' => $pago->id,
                'monto' => $pago->monto,
                'pendiente' => $pago->estaPendiente(),
            ],
        ], 201);
    }

    /** Marca un pago como pagado. */
    public function marcarPagado(Pago $pago, Request $peticion): JsonResponse
    {
        $this->authorize('marcarPagado', $pago);

        $pago->pagado_en = $peticion->input('pagado_en') ?? now();
        $pago->save();

        return response()->json([
            'mensaje' => 'Pago marcado como realizado.',
            'pago' => [
                'id' => $pago->id,
                'pagado_en' => $pago->pagado_en?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Anula un cobro mal hecho.
     *
     * No lo borra: queda la fila con `anulado` en true. Que alguien haya
     * cobrado y después se anulara vale más como dato que la ausencia del
     * registro.
     */
    public function anular(Pago $pago, Request $peticion): JsonResponse
    {
        $this->authorize('anular', $pago);

        $pago->anulado = true;
        $pago->save();

        return response()->json([
            'mensaje' => 'Cobro anulado.',
            'motivo' => $peticion->input('motivo'),
        ]);
    }

    /**
     * @param  list<Pago>  $pagos
     */
    private function saldoPendiente(array $pagos): int
    {
        return (int) collect($pagos)
            ->filter(fn (Pago $pago): bool => $pago->estaPendiente())
            ->sum(fn (Pago $pago): int => $pago->monto ?? 0);
    }
}