<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscripción: un alumno suscrito a un plan, con su historial.
 *
 * `precio_congelado` es una fotografía del precio al momento de contratação.
 * Existe porque si el precio del plan sube en enero, laInscripción de diciembre
 * tiene que seguir mostrando lo que se cobró en diciembre. Un `JOIN` al precio
 * vigente habría reescrito el histórico.
 *
 * NOTA SOBRE EL HISTÓRICO DE PAGOS
 * ---------------------------------
 * La inscripción NO tiene columna `pagado`. Los pagos viven en `pagos`, uno por
 * período. La pregunta que hace la escuela es "¿quién pagó?", y con una sola
 * columna que se sobreescribe cada mes, esa respuesta se pierde en cuanto llega
 * el cobro siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $tabla->foreignUuid('plan_id')->constrained('planes')->restrictOnDelete();

            $tabla->date('iniciada_en');

            // NULL = inscripción abierta, sin fecha de término pactada. El
            // sistema no la inventa: una inscripción viva no caduca sola.
            $tabla->date('terminada_en')->nullable();

            $tabla->string('estado', 20)->default('activa');

            $tabla->integer('precio_congelado')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'alumno_id'], 'inscripciones_por_alumno');
            $tabla->index(['tenant_id', 'estado'], 'inscripciones_por_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};