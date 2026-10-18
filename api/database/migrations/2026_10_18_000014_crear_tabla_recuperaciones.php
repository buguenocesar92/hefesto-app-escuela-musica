<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recuperaciones: la deuda de clases cuando un alumno falta.
 *
 * `vence_el` CALCULADO, NO FIJO
 * -----------------------------
 * La fecha de vencimiento sale de `ESCUELA_MUSICA_DIAS_RECUPERACION` (ver
 * `config/escuela-musica.php`). Si ese valor no está configurado, `vence_el`
 * queda en NULL: la clase recuperada NO caduca.
 *
 * Con `vence_el` en NULL la deuda se acumula sin que nadie la vea, que es
 * exactamente el problema que el sistema tiene que hacer visible. Por eso hay
 * dos saldo de por vida:
 *
 *   - `App\Services\Recuperacion\SaldoDeRecuperaciones` suma las pendientes.
 *   - `escuela-musica:vencer-recuperaciones` las lista una por una.
 *
 * Con la regla configurada, `escuela-musica:vencer-recuperaciones` marca como
 * `vencida` las que pasaron la fecha, y eso genera la movimiento de restitución
 * en `movimientos_clase_plan`.
 *
 * `clase_nueva_id` se llena cuando la recuperación se reprograma: es la clase
 * por la que finalmente se recupera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recuperaciones', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->foreignUuid('alumno_id')->constrained('alumnos')->cascadeOnDelete();

            // La clase en la que faltó.
            $tabla->foreignUuid('clase_origen_id')->constrained('clases')->cascadeOnDelete();

            // La asistencia que originó la recuperación. NULL cuando la
            // recuperación se creó a mano (por ejemplo, un acuerdo con la
            // familia), sin que hubiera una ausencia registrada.
            $tabla->foreignUuid('asistencia_origen_id')->nullable()->constrained('asistencias')->nullOnDelete();

            // La clase por la que se recupera. NULL mientras está pendiente.
            $tabla->foreignUuid('clase_nueva_id')->nullable()->constrained('clases')->nullOnDelete();

            // `pendiente`, `agendada`, `usada`, `vencida` o `anulada`.
            $tabla->string('estado', 20)->default('pendiente');

            $tabla->timestampTz('creada_en');

            // NULL = sin caducidad, porque la escuela no definió el plazo.
            // Ver la nota de arriba.
            $tabla->timestampTz('vence_el')->nullable();

            $tabla->timestampTz('usada_en')->nullable();

            $tabla->text('motivo')->nullable();

            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'alumno_id'], 'recuperaciones_por_alumno');

            // Consulta del comando de caducidad: pendientes que ya pasaron la
            // fecha, y pendientes sin fecha de vencimiento (la deuda que no
            // caduca).
            $tabla->index(['tenant_id', 'estado', 'vence_el'], 'recuperaciones_por_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recuperaciones');
    }
};