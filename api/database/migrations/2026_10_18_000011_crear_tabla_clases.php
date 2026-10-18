<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clases: instancias concretas que ocurren en un horario, con un docente y en
 * una sala.
 *
 * UNA CLASE ES UN EVENTO EN EL CALENDARIO, NO UN CURSO
 * ---------------------------------------------------
 * "Piano a las 18:00 los martes" es un curso o una oferta; "la clase de piano
 * de la semana del 3 de noviembre, de 18:00 a 19:00, con la profesora X y en la
 * sala 2" es una fila de esta tabla. La asistencia y el consumo de clases del
 * plan se anotan contra la fila, porque contra el curso no se puede saber si la
 * persona fue el 3 o el 10 de noviembre.
 *
 * `inicio` y `termina` se guardan como marcas de tiempo con zona. El choque de
 * agenda se calcula sobre estos dos campos; ver
 * `App\Services\Agenda\DetectorDeSolapamiento`.
 *
 * No se superpone un índice único sobre (docente, inicio): PostgreSQL no puede
 * expresar "intervalos que se pisan" en un índice normal, y un índice
 * aproximado daría una garantía falsa. El solapamiento se comprueba en la
 * aplicación y se prueba en `tests/Feature/Agenda`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clases', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('docente_id')->constrained('docentes')->restrictOnDelete();
            $tabla->foreignUuid('sala_id')->constrained('salas')->restrictOnDelete();

            $tabla->string('nombre', 160);

            $tabla->timestampTz('inicio');
            $tabla->timestampTz('termina');

            // Aforo. NULL = sin límite declarado.
            $tabla->integer('capacidad')->nullable();

            $tabla->string('estado', 20)->default('agendada');

            $tabla->text('notas')->nullable();

            $tabla->timestampsTz();

            // El índice de la agenda: por escuela y por inicio.
            $tabla->index(['tenant_id', 'inicio'], 'clases_agenda_por_inicio');

            // Los otros dos son los que se cruzan en la comprobación de
            // solapamiento.
            $tabla->index(['tenant_id', 'docente_id', 'inicio'], 'clases_agenda_por_docente');
            $tabla->index(['tenant_id', 'sala_id', 'inicio'], 'clases_agenda_por_sala');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clases');
    }
};