<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de sesiones de Laravel.
 *
 * NO lleva `tenant_id` ni RLS, y la razón es distinta a la de `escuelas`: esta
 * tabla la resuelve Laravel por identificador de sesión, que es un valor
 * aleatorio y no un dato de negocio. `sessions` nunca contiene información de
 * alumnos ni de pagos, solo el identificador de la sesión y su contenido
 * serializado (el identificador del usuario).
 *
 * Ponerle RLS a esta tabla rompería el ingreso: la sesión se escribe ANTES de
 * que exista contexto de escuela, que es justo cuando se necesita crear la
 * fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $tabla): void {
            $tabla->string('id')->primary();

            // Usuario autenticado. NULL mientras la sesión no está iniciada.
            $tabla->foreignUuid('user_id')->nullable()->index();

            $tabla->string('ip_address', 45)->nullable();
            $tabla->text('user_agent')->nullable();
            $tabla->longText('payload');

            // Laravel decide si la sesión sigue viva.
            $tabla->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};