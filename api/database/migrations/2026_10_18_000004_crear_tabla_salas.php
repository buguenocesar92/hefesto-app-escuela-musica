<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salas: los espacios físicos donde se imparte una clase.
 *
 * El choque de agenda entre dos clases no es solo de docente: dos clases
 * distintas pueden disputarse la misma sala. Por eso la sala es una entidad
 * de primer orden y no un texto dentro de la clase. Ver
 * `App\Services\Agenda\DetectorDeSolapamiento`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salas', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->string('nombre', 120);

            // Aforo. Se acepta nulo: hay salas cuya capacidad la escuela no
            // tiene escrita en ningún parte, y es mejor nulo que inventado.
            $tabla->integer('capacidad')->nullable();

            // Una sala en mantenimiento no aparece en el selector de agenda.
            $tabla->boolean('activa')->default(true);

            $tabla->text('observacion')->nullable();

            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'nombre'], 'salas_nombre_unico_por_escuela');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salas');
    }
};