<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos: cuotas de plan y pagos por clase suelta, en la misma tabla.
 *
 * POR QUÉ UNA SOLA TABLA PARA LAS DOS COSAS
 * -----------------------------------------
 * El estado de cuenta que mira la escuela es de la persona, no del tipo de
 * cargo: "¿me debe algo?". Si las cuotas y las clases sueltas vivieran en
 * tablas distintas, cada pantalla de estado de cuenta tendría que consultar
 * las dos y sumar en código, y en el momento en que alguien olvide sumar una,
 * el saldo mostrado queda mal.
 *
 * `concepto` dice de qué tipo es el cargo: `cuota_plan` o `clase_suelta`.
 *
 * `pagado_en` en NULL significa pendiente. `anulado` permite corregir un cobro
 * mal hecho sin borrarlo: el registro de que alguien cobró y después se
 * anuló vale más que la ausencia del registro.
 *
 * El monto va en NULL cuando la escuela aún no lo ha definido. No hay valor de
 * ejemplo en ningún seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('alumno_id')->constrained('alumnos')->cascadeOnDelete();

            // NULL para el pago por clase, que no viene de un plan.
            $tabla->foreignUuid('inscripcion_id')->nullable()->constrained('inscripciones')->nullOnDelete();

            $tabla->string('concepto', 20)->default('cuota_plan');

            $tabla->integer('monto')->nullable();

            // Período que cubre la cuota. NULL para el pago por clase, que no
            // cubre un período mensual.
            $tabla->date('periodo_desde')->nullable();
            $tabla->date('periodo_hasta')->nullable();

            // NULL = pendiente de pago. Esta es la columna que decide quién
            // está moroso.
            $tabla->timestampTz('pagado_en')->nullable();

            $tabla->string('metodo', 30)->nullable();

            // Folio del comprobante. Lo llena la escuela: no se genera aquí
            // porque emitir un número de documento es una decisión suya.
            $tabla->string('referencia', 80)->nullable();

            $tabla->boolean('anulado')->default(false);

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'alumno_id'], 'pagos_por_alumno');
            $tabla->index(['tenant_id', 'pagado_en'], 'pagos_por_pagado');
            $tabla->index(['tenant_id', 'anulado'], 'pagos_por_anulado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};