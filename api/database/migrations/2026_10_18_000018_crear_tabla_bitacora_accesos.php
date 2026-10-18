<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de accesos: quién entró, a qué hora y desde dónde.
 *
 * Es el registro que permite responder "¿quién consultó los pagos de esta
 * familia?". Por eso tiene `tenant_id` NOT NULL y RLS: una bitácora sin
 * aislamiento sería un archivo con datos de acceso de todas las escuelas.
 *
 * NOTA SOBRE LOS INTENTOS FALLIDOS
 * -------------------------------
 * Un intento de ingreso que falla ocurre ANTES de que exista contexto de
 * escuela, así que no se puede escribir en esta tabla: la escritura fallaría
 * con la RLS activa. El sistema no lo oculta: lo registra en el log de la
 * aplicación (`canal daily`), que no tiene datos de personas. Ver
 * docs/07-SEGURIDAD-Y-LEY-19628.md, sección "Qué queda fuera de la bitácora".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_accesos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();

            $tabla->string('metodo', 10);
            $tabla->string('ruta', 240);

            // Código de respuesta. Sirve para distinguir "cerró sesión" de
            // "rechazado".
            $tabla->integer('resultado');

            // Dirección IP y agente de usuario. Son datos personales: se
            // guardan para seguridad y se eliminan con la retención
            // configurada.
            $tabla->string('direccion_ip', 45)->nullable();
            $tabla->string('agente_usuario', 240)->nullable();

            $tabla->timestampTz('ocurrido_en');

            // Marca de tiempo en vez de timestampscreated_at: la bitácora se
            // escribe una vez y no se edita nunca.
            $tabla->index(['tenant_id', 'ocurrido_en'], 'bitacora_por_fecha');
            $tabla->index(['tenant_id', 'usuario_id'], 'bitacora_por_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_accesos');
    }
};