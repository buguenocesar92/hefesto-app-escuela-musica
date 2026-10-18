<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\EstadoRecuperacion;
use App\Models\Recuperacion;
use App\Services\Recuperacion\SaldoDeRecuperaciones;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Marca como vencidas las recuperaciones que pasaron su fecha.
 *
 * POR QUÉ ESTO VA DIFERIDO
 * ------------------------
 * La caducidad no debería depender de que alguien abra la aplicación. Si el
 * trabajo se hace al momento de marcar la ausencia, y ese día la aplicación
 * estuvo apagada, la deuda queda vencida sin que nadie lo sepa.
 *
 * Por eso el trabajo corre desde la programación de `routes/console.php` y no
 * desde la petición.
 *
 * UNA RECUPERACIÓN SIN FECHA NO CADUCA
 * -----------------------------------
 * Este trabajo solo toca las que tienen `vence_el`. Si la escuela no configuró
 * la vigencia, no hay nada que caducar y las deudas siguen ahí, a la vista en el
 * tablero. No se les pone una fecha por su cuenta.
 */
class VencerRecuperacionesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $tenantId) {}

    public function handle(SaldoDeRecuperaciones $saldo): void
    {
        $contexto = app(\App\Services\Tenancy\ContextoEscuela::class);

        $contexto->ejecutarAislado($this->tenantId, function () use ($saldo): void {
            $vencidas = $saldo->vencidas();

            foreach ($vencidas as $recuperacion) {
                /** @var Recuperacion $recuperacion */
                $recuperacion->estado = EstadoRecuperacion::Vencida;
                $recuperacion->save();

                Log::info('Recuperación vencida.', [
                    'recuperacion_id' => $recuperacion->id,
                    'vencia_el' => $recuperacion->vence_el?->toIso8601String(),
                ]);
            }

            if ($vencidas->isNotEmpty()) {
                Log::info('Se marcaron recuperaciones vencidas.', [
                    'cantidad' => $vencidas->count(),
                ]);
            }
        });
    }
}