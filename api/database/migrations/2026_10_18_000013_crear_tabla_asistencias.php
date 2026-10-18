<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asistencia: quién vino y quién no vino a una clase concreta.
 *
 * LA COLUMNA `consume_clase_plan` ES LA QUE NO SE INVENTA
 * ------------------------------------------------------
 * Es un booleano NULLABLE a propósito, y su valor lo decide la escuela, no el
 * software:
 *
 *   - `true`: esta ausencia descontó una clase del plan mensual.
 *   - `false`: esta ausencia NO descontó nada del plan.
 *   - NULL: la regla no está configurada, así que el sistema NO sabe qué
 *     hacer y no toca el cupo del plan.
 *
 * Ese tercer estado es lo que separa este sistema de uno que elige por
 * intuición. "La ausencia consume la clase" y "la ausencia no consume la clase"
 * son las dos respuestas defendibles, cambian el ingreso de la escuela y no se
 * pueden deducir de nada. Ver docs/05-CONFIGURACION.md.
 *
 * Al registrar la ausencia se guarda SIEMPRE el valor de la regla tal como
 * estaba en ese momento. Si más adelante la escuela cambia la regla, las
 * asistencias viejas siguen diciendo qué pasó con ellas y no se reescriben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('clase_id')->constrained('clases')->cascadeOnDelete();
            $tabla->foreignUuid('alumno_id')->constrained('alumnos')->cascadeOnDelete();

            // `presente` u `ausente`. No hay un estado `justificado` porque
            // la escuela no definió esas categorías: agregar una sería
            // inventar una regla de negocio.
            $tabla->string('estado', 20);

            // Tri-estado. Ver la nota de arriba: es el núcleo del problema.
            $tabla->boolean('consume_clase_plan')->nullable();

            $tabla->foreignUuid('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $tabla->timestampTz('registrado_en');

            $tabla->text('observacion')->nullable();

            $tabla->timestampsTz();

            // Una asistencia por alumno y clase: corregirla se hace sobre la
            // misma fila, no agregando una segunda.
            $tabla->unique(['clase_id', 'alumno_id'], 'asistencias_unica_por_clase_y_alumno');

            $tabla->index(['tenant_id', 'alumno_id'], 'asistencias_por_alumno');
        });

        // Índice PARCIAL para las ausencias cuya regla quedó sin definir.
        //
        // Se escribe a mano con `DB::statement` porque el constructor de
        // migraciones de Laravel no expone índices parciales: su tercer
        // parámetro en `index()` es el algoritmo (como `btree` o `hash`), no
        // una condición. Pasarle una columna ahí produciría SQL inválido.
        //
        // Es la consulta que responde "¿qué tengo que revisar?".
        DB::statement(
            'CREATE INDEX asistencias_ausencias_por_definir
             ON public.asistencias (tenant_id, registrado_en)
             WHERE consume_clase_plan IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};