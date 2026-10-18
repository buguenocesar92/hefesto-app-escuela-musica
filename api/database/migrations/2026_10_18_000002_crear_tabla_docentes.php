<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Docentes: las personas que dan las clases.
 *
 * Un docente NO es un usuario. La misma persona puede dar clases en dos
 * escuelas y en ninguna tener sesión abierta. Separar las dos cosas evita que
 * "puedo entrar al sistema" y "imparto clases" sean la misma columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docentes', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->string('nombre_completo', 160);

            // El correo es de contacto de la escuela, no una credencial: no se
            // usa para entrar al sistema. El ingreso lo hace `usuarios`.
            $tabla->string('correo', 160)->nullable();

            // Instrumento o disciplina. Texto libre y no tabla aparte: la
            // escuela no necesita filtrar por ello y mantener una tabla más no
            // compra nada.
            $tabla->string('especialidad', 120)->nullable();

            $tabla->boolean('activo')->default(true);

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docentes');
    }
};