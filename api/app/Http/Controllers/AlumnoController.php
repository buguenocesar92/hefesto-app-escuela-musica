<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlumnoRequest;
use App\Models\Alumno;
use App\Services\Planes\ConsumidorDeClases;
use App\Services\Recuperacion\SaldoDeRecuperaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alumnos.
 *
 * CUANDO UNA POLÍTICA RECHAZA, LA RESPUESTA ES 404
 * -------------------------------------------------
 * Ver `bootstrap/app.php`. Un 403 confirmaría que el identificador existe en
 * alguna escuela, que es justo lo que el aislamiento no debe revelar.
 */
class AlumnoController extends Controller
{
    public function index(Request $peticion): JsonResponse
    {
        $this->authorize('verListado', Alumno::class);

        $busqueda = trim((string) $peticion->input('buscar', ''));

        $consulta = Alumno::query()
            ->when($busqueda !== '', function ($q) use ($busqueda): void {
                $q->where(function ($interno) use ($busqueda): void {
                    $interno->where('nombre', 'ilike', '%' . $busqueda . '%')
                        ->orWhere('apellido', 'ilike', '%' . $busqueda . '%')
                        ->orWhere('rut', 'ilike', '%' . $busqueda . '%');
                });
            })
            ->orderBy('apellido')
            ->orderBy('nombre');

        $porPagina = (int) $peticion->input('por_pagina', 50);
        $porPagina = max(1, min($porPagina, (int) config('escuela-musica.por_pagina_maximo', 100)));

        $pagina = $consulta->paginate($porPagina);

        return response()->json([
            'datos' => collect($pagina->items())->map(fn (Alumno $alumno): array => [
                'id' => $alumno->id,
                'nombre' => $alumno->nombre,
                'apellido' => $alumno->apellido,
                'nombre_completo' => $alumno->nombreCompleto(),
                'activo' => $alumno->activo,
                // La lista no trae dirección ni teléfono: son datos de contacto
                // y no hacen falta para llamar a la clase.
                'tiene_autorizacion' => $alumno->tieneAutorizacionRegistrada(),
            ])->all(),
            'meta' => [
                'pagina' => $pagina->currentPage(),
                'paginas' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ],
        ]);
    }

    public function show(Alumno $alumno): JsonResponse
    {
        $this->authorize('ver', $alumno);

        $consumidor = app(ConsumidorDeClases::class);
        $saldo = app(SaldoDeRecuperaciones::class);

        $puedeVerFicha = request()->user()?->can('verFichaCompleta', $alumno) ?? false;

        return response()->json([
            'alumno' => [
                'id' => $alumno->id,
                'nombre' => $alumno->nombre,
                'apellido' => $alumno->apellido,
                'nombre_completo' => $alumno->nombreCompleto(),
                'activo' => $alumno->activo,
                'fecha_ingreso' => $alumno->fecha_ingreso?->toDateString(),
                'es_menor_edad' => $alumno->esMenorEdad(),
                'tiene_autorizacion' => $alumno->tieneAutorizacionRegistrada(),
                // Los datos personales solo van si quien pregunta tiene por
                // qué conocerlos.
                'datos_personales' => $puedeVerFicha ? [
                    'rut' => $alumno->rut,
                    'fecha_nacimiento' => $alumno->fecha_nacimiento?->toDateString(),
                    'direccion' => $alumno->direccion,
                    'telefono' => $alumno->telefono,
                    'correo' => $alumno->correo,
                ] : null,
            ],
            'plan' => [
                'cupo_restante' => $consumidor->cupoRestante($alumno),
                'clases_consumidas' => $consumidor->consumidas($alumno),
            ],
            'recuperaciones' => $saldo->saldoDe($alumno),
        ]);
    }

    public function store(StoreAlumnoRequest $peticion): JsonResponse
    {
        $alumno = Alumno::query()->create($peticion->validated());

        return response()->json([
            'alumno' => [
                'id' => $alumno->id,
                'nombre_completo' => $alumno->nombreCompleto(),
            ],
            'mensaje' => 'Alumno registrado.',
        ], 201);
    }

    public function update(StoreAlumnoRequest $peticion, Alumno $alumno): JsonResponse
    {
        $this->authorize('actualizar', $alumno);

        $alumno->update($peticion->validated());

        return response()->json([
            'mensaje' => 'Alumno actualizado.',
            'alumno' => [
                'id' => $alumno->id,
                'nombre_completo' => $alumno->nombreCompleto(),
            ],
        ]);
    }
}