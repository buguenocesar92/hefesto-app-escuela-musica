<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo entre un alumno y su responsable, con la autorización.
 *
 * LA AUTORIZACIÓN DEL ADULTO RESPONSABLE
 * -------------------------------------
 * Registrar a un menor en clases implica tratar sus datos personales. Eso no
 * se hace por la sola voluntad de la escuela: hace falta que el adulto
 * responsable lo autorice. Por eso la autorización es una COLUMNA de esta
 * tabla y no una nota de la ficha: tiene fecha y se puede mostrar.
 *
 * `autorizacion_vence_el` queda en NULL a propósito.
 *
 * El impulso de llenarlo con un plazo de un año, que es lo que se ve en
 * formularios de otras aplicaciones, no se sigue. El plazo por el que una
 * autorización de tratamiento de datos de un menor sigue vigente es una
 * decisión del cliente, con su responsable legal y su política de
 * tratamiento; inventar un número aquí sería afirmar algo falso sobre una
 * obligación legal. NULL significa "la escuela todavía no lo ha definido".
 *
 * Ver docs/06-SEGURIDAD-Y-LEY-19628.md, sección "Lo que NO se puso".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumno_responsables', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $tabla->foreignUuid('responsable_id')->constrained('responsables')->cascadeOnDelete();

            // Se repite la relación acá porque un mismo adulto puede estar
            // anotado en dos alumnos con relaciones distintas, y el dato
            // pertenece al vínculo, no a la persona.
            $tabla->string('relacion', 80);

            // Sin esto no se sabe si la autorización por separado es del
            // adulto legal o de un tercero que paga.
            $tabla->boolean('es_responsable_legal')->default(false);

            $tabla->boolean('autorizacion_registrada')->default(false);
            $tabla->timestampTz('autorizacion_registrada_en')->nullable();

            // NULL = plazo no definido por la escuela. Ver la nota de arriba.
            $tabla->date('autorizacion_vence_el')->nullable();

            // Referencia del documento firmado. Texto, no archivo: el archivo
            // se guarda fuera de la base (ver .gitignore de la API).
            $tabla->string('autorizacion_documento', 160)->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['alumno_id', 'responsable_id'], 'alumno_responsables_unico');

            // Consulta frecuente: "este alumno, ¿tiene a alguien que haya
            // autorizado?".
            $tabla->index(['tenant_id', 'autorizacion_registrada'], 'alumno_responsables_autorizacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_responsables');
    }
};