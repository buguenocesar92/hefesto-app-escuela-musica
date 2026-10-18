<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préstamos y arriendos de instrumentos: qué tiene cada alumno y cómo volvió.
 *
 * `estado_devolucion` responde la pregunta que más importa cuando algo falta:
 * ¿volvió entero? Acepta `bueno`, `con_danos` o `incompleto`, y va en NULL
 * mientras el instrumento sigue fuera de la escuela.
 *
 * Un instrumento devuelto con daños NO se borra: queda el préstamo con su
 * estado. Si se borrara el registro al hacer la devolución, se perdería justo
 * el dato que sirve para reclamar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos_instrumento', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('instrumento_id')->constrained('instrumentos')->cascadeOnDelete();
            $tabla->foreignUuid('alumno_id')->constrained('alumnos')->cascadeOnDelete();

            // `prestamo` (se presta) o `arriendo` (se cobra por usarlo). La
            // diferencia la hace la escuela; el sistema solo la registra.
            $tabla->string('tipo', 20)->default('prestamo');

            $tabla->timestampTz('entregado_en');

            // NULL = el instrumento todavía no volvió.
            $tabla->timestampTz('devuelto_en')->nullable();

            $tabla->string('estado_devolucion', 20)->nullable();

            $tabla->text('observacion')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'alumno_id'], 'prestamos_por_alumno');

            // Índice para "¿qué instrumentos no han vuelto?".
            $tabla->index(['tenant_id', 'devuelto_en'], 'prestamos_sin_devolver');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos_instrumento');
    }
};