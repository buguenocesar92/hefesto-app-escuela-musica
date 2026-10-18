<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscripción de un alumno a una clase concreta.
 *
 * Une `alumnos` con `clases`. Necesaria porque el mismo alumno puede estar
 * inscrito en varias clases y la misma clase puede tener muchos alumnos, y
 * porque la asistencia se registra por esta pareja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones_clase', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('clase_id')->constrained('clases')->cascadeOnDelete();
            $tabla->foreignUuid('alumno_id')->constrained('alumnos')->cascadeOnDelete();

            $tabla->timestampsTz();

            $tabla->unique(['clase_id', 'alumno_id'], 'inscripciones_clase_unico');

            $tabla->index(['tenant_id', 'alumno_id'], 'inscripciones_clase_por_alumno');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones_clase');
    }
};