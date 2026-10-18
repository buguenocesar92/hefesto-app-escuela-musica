<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instrumentos: lo que la escuela presta o arrienda.
 *
 * `estado` es el estado del instrumento EN LA ESCUELA, no el del alumno. El
 * estado en que vuelve lo guarda el préstamo (`prestamos_instrumento`), que es
 * donde queda: un piano que vuelve con una tecla hundida sigue siendo de la
 * escuela y necesita reparación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrumentos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->string('nombre', 160);
            $tabla->string('tipo', 80)->nullable();

            // `disponible`, `prestado`, `arrendado`, `mantencion` o `baja`.
            $tabla->string('estado', 20)->default('disponible');

            // Marca del fabricante. Texto libre: nadie necesita filtrar por
            // marcas para poder prestárselo a un alumno.
            $tabla->string('marca', 80)->nullable();

            // Número de serie o etiqueta interna. Sirve para no confundir dos
            // guitarra iguales.
            $tabla->string('codigo_interno', 60)->nullable();

            $tabla->text('observacion')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'estado'], 'instrumentos_por_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrumentos');
    }
};