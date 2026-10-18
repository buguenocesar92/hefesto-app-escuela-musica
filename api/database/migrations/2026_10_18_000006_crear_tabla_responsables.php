<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsables: los adultos que responden por un alumno.
 *
 * Se guardan aparte del alumno porque una misma persona puede ser responsable
 * de varios hermanos, y duplicar sus datos en cada ficha los desincroniza a la
 * primera corrección.
 *
 * También acá viven los datos del adulto, que son datos personales de una
 * persona mayor: se tratan con el mismo cuidado y no se exponen en las
 * pantallas del docente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responsables', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->string('nombre_completo', 160);
            $tabla->string('rut', 20)->nullable();
            $tabla->string('telefono', 40)->nullable();
            $tabla->string('correo', 160)->nullable();
            $tabla->string('direccion', 240)->nullable();

            // Parentesco o relación con el alumno: madre, padre, abuelo,
            // tío,representante legal. Texto libre porque las familias reales
            // no siempre caben en una lista.
            $tabla->string('relacion', 80);

            $tabla->boolean('es_responsable_legal')->default(false);

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'nombre_completo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsables');
    }
};