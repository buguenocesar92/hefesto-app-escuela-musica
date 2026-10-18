<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes mensuales: el paquete que compra un alumno.
 *
 * Esta tabla es la diferencia entre el ingreso estable y el variable, y la
 * diferencia se decide POR ALUMNO, no por curso: el mismo alumno puede estar
 * en un plan y pagar las clases sueltas el mes siguiente.
 *
 * LOS TRES NÚMEROS QUE IMPORTAN SON NULLABLE, Y NO POR DESCUIDO
 * -----------------------------------------------------------
 * `clases_incluidas`, `precio` y `vigencia_dias` admiten NULL. NULL no es
 * "cero": es "la escuela todavía no lo ha definido".
 *
 * Con `clases_incluidas` en NULL, el sistema NO sabe cuántas clases da el plan,
 * así que NO resta clases del cupo al marcar una asistencia. Prefiere no
 * descontar antes que descontar una cantidad que nadie definió.
 *
 * Con `precio` en NULL no hay monto que cobrar y el estado de cuenta del alumno
 * no muestra cuota. No hay precio de ejemplo en ningún lado: un número inventado
 * en un seeder termina en una factura.
 *
 * Ver docs/05-CONFIGURACION.md y docs/06-PRUEBAS-Y-ESTADO.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->string('nombre', 120);
            $tabla->text('descripcion')->nullable();

            // Clases incluidas en el período. NULL = cupo no definido.
            $tabla->integer('clases_incluidas')->nullable();

            // Monto en pesos chilenos. NULL = precio no definido.
            $tabla->integer('precio')->nullable();

            // Duración del período en días (30, 31 o lo que la escuela
            // maneje). NULL = período no definido.
            $tabla->integer('vigencia_dias')->nullable();

            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};