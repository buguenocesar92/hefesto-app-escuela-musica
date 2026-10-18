<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de escuelas. Es la tabla TENANT.
 *
 * NO lleva `tenant_id` ni Row Level Security, y esa es una excepción
 * consciente: se consulta en la pantalla de entrada, cuando todavía no hay
 * sesión y por lo tanto no existe `app.tenant_id` que comparar. La
 * compensación es la vista `vw_escuelas_publicas` (migración
 * 2026_10_18_000021), que es la que consulta el selector de ingreso.
 *
 * Ver docs/03-MULTITENANCY-Y-ROLES.md, sección 4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escuelas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();

            // Identificador corto y legible que aparece en la URL de la
            // aplicación. Es único: es lo que separa una escuela de otra.
            $tabla->string('slug', 80)->unique();

            $tabla->string('nombre', 160);

            // Razón social y RUT: una PyME emite factura. Son datos de la
            // escuela, no de personas, y no se rellenan de muestra.
            $tabla->string('razon_social', 160)->nullable();
            $tabla->string('rut', 20)->nullable();

            // Una escuela dada de baja deja de aparecer en el selector de
            // ingreso, pero conserva sus datos: no se borra una escuela con
            // asistencia y pagos encima.
            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escuelas');
    }
};