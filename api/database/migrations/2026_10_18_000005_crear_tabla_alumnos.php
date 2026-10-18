<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumnos de la escuela.
 *
 * GRAN PARTE DE ESTA TABLA SON DATOS DE MENORES DE EDAD
 * ---------------------------------------------------
 * Nombre, RUT y dirección de un menor son datos personales. La Ley 19.628 los
 * protege con el mismo régimen que los de un adulto, y a veces con más
 * cuidado en la práctica: un menor no puede ejercer por sí mismo el derecho de
 * acceso, y la autorización viene del adulto responsable.
 *
 * Esa autorización NO vive en esta tabla a propósito: vive en
 * `alumno_responsables`, que es donde se sabe QUIÉN autorizó y sobre qué
 * alumno.
 *
 * NO se agrega un campo `retencion_hasta` con un plazo puesto ahí. Ese plazo
 * depende de la política de tratamiento de datos de la escuela, que es una
 * decisión del cliente, no del software. Ver docs/06-SEGURIDAD-Y-LEY-19628.md,
 * sección "Lo que NO se puso".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->string('nombre', 120);
            $tabla->string('apellido', 120);

            // RUT: se acepta nulo porque un menor puede empezar clases antes
            // de que la familia entregue el documento. El índice único por
            // escuela permite varios nulos, que es lo que se quiere: varios
            // alumnos sin RUT en la misma escuela no colisionan.
            $tabla->string('rut', 20)->nullable();

            $tabla->date('fecha_nacimiento')->nullable();

            // Dirección y teléfono: se piden porque la escuela necesita
            // localizar a la familia ante una emergencia, no por curiosidad.
            $tabla->string('direccion', 240)->nullable();
            $tabla->string('telefono', 40)->nullable();

            // El correo es del adulto responsable cuando el alumno es menor.
            // El sistema no exige que sea del menor.
            $tabla->string('correo', 160)->nullable();

            $tabla->date('fecha_ingreso')->nullable();

            $tabla->boolean('activo')->default(true);

            $tabla->text('observacion')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'rut'], 'alumnos_rut_unico_por_escuela');
            $tabla->index(['tenant_id', 'activo']);
            $tabla->index(['tenant_id', 'apellido', 'nombre'], 'alumnos_orden_por_apellido');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};