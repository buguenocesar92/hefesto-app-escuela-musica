<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Movimientos de clase del plan: el libro de consumo.
 *
 * POR QUÉ UN LIBRO Y NO UN CONTADOR
 * ---------------------------------
 * La pregunta que hace la escuela es "¿cuántas clases le quedan a este alumno?".
 * Un contador en `planes` o en `inscripciones` respondería, pero no dejaría
 * rastro de POR QUÉ bajó. Con el libro se puede reconstruir el saldo clase por
 * clase: el consumo de hoy, la devolución de una recuperación que se venció, el
 * ajuste que hizo la administración.
 *
 * Tipos:
 *   - `consumo`: una clase se descontó del plan (presencia, o ausencia cuando
 *     la regla dice que consume).
 *   - `devolucion`: se devolvió una clase al cupo (recuperación usada).
 *   - `ajuste`: corrección manual. Se deja traza del motivo en `motivo`.
 *
 * `motivo` es obligatorio por regla de negocio para `ajuste`: un ajuste sin
 * explicación no sirve para nada. Ver la restricción de abajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_clase_plan', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('alumno_id')->constrained('alumnos')->cascadeOnDelete();

            // NULL cuando el movimiento no viene de una inscripción (por
            // ejemplo, un ajuste sobre una clase suelta).
            $tabla->foreignUuid('inscripcion_id')->nullable()->constrained('inscripciones')->nullOnDelete();

            // De dónde sale el movimiento: la clase y la asistencia que lo
            // causaron. NULL en un ajuste manual.
            $tabla->foreignUuid('clase_id')->nullable()->constrained('clases')->nullOnDelete();
            $tabla->foreignUuid('asistencia_id')->nullable()->constrained('asistencias')->nullOnDelete();

            $tabla->string('tipo', 20);

            $tabla->text('motivo')->nullable();

            $tabla->timestampTz('ocurre_en');

            $tabla->timestampsTz();

            // Un ajuste sin motivo no es auditable: se rechaza en la base, no
            // solo en la interfaz. Ver la restricción CHECK de abajo.
            $tabla->index(['tenant_id', 'alumno_id'], 'movimientos_por_alumno');
            $tabla->index(['tenant_id', 'ocurre_en'], 'movimientos_por_fecha');
            $tabla->index(['tenant_id', 'tipo'], 'movimientos_por_tipo');

            DB::statement(
                "ALTER TABLE public.movimientos_clase_plan
                 ADD CONSTRAINT movimientos_clase_plan_tipo_valido
                 CHECK (tipo IN ('consumo', 'devolucion', 'ajuste'))"
            );

            DB::statement(
                "ALTER TABLE public.movimientos_clase_plan
                 ADD CONSTRAINT movimientos_clase_plan_ajuste_con_motivo
                 CHECK (tipo <> 'ajuste' OR (motivo IS NOT NULL AND length(btrim(motivo)) > 0))"
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_clase_plan');
    }
};