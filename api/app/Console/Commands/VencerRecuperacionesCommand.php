<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\VencerRecuperacionesJob;
use App\Models\Escuela;
use App\Services\Recuperacion\CaducidadDeRecuperacion;
use App\Services\Recuperacion\SaldoDeRecuperaciones;
use App\Services\Tenancy\ContextoEscuela;
use Illuminate\Console\Command;

/**
 * Revisa la deuda de clases de cada escuela.
 *
 * HACE DOS COSAS DISTINTAS, Y DEPENDE DE LA CONFIGURACIÓN
 * ------------------------------------------------------
 *
 *  1. Con `ESCUELA_MUSICA_DIAS_RECUPERACION` configurado: muestra las
 *     recuperaciones que pasaron su fecha y, si se pasa `--aplicar`, encola el
 *     trabajo que las marca como `vencida`.
 *
 *  2. Sin esa configuración: NO puede vencer nada, porque no hay fecha. Lo que
 *     hace es LISTAR la deuda sin fecha, una por una, para que la escuela la
 *     revise a mano.
 *
 * Que el comando no pueda vencer nada sin configuración no es un fallo: es la
 * consecuencia de que nadie definió el plazo. Un plazo inventado habría
 * caducado deudas que la escuela todavía le debe a una familia.
 *
 * Por omisión NO modifica nada. Hay que pedir `--aplicar` explícitamente,
 * porque vencer una deuda es una acción que se ve desde la casa del alumno.
 */
class VencerRecuperacionesCommand extends Command
{
    protected $signature = 'escuela-musica:vencer-recuperaciones
                            {--escuela= : Limitar a una escuela por su identificador}
                            {--aplicar : Encolar el trabajo que marca las vencidas como vencidas}';

    protected $description = 'Lista la deuda de recuperaciones y, con --aplicar, vence la que ya pasó su fecha.';

    public function handle(
        ContextoEscuela $contexto,
        SaldoDeRecuperaciones $saldo,
        CaducidadDeRecuperacion $caducidad
    ): int {
        $escuelas = $this->resolveEscuelas();

        if ($escuelas === []) {
            $this->warn('No hay escuelas activas a las que revisar.');

            return self::SUCCESS;
        }

        $hayCaducidad = $caducidad->hayCaducidadConfigurada();

        if (! $hayCaducidad) {
            $this->warn('No hay caducidad configurada (ESCUELA_MUSICA_DIAS_RECUPERACION).');
            $this->line('No se puede vencer nada: sin plazo, no hay fecha que comparar.');
            $this->line('');
        }

        $aplicar = (bool) $this->option('aplicar');

        foreach ($escuelas as $escuela) {
            $this->line("Escuela: {$escuela->nombre} ({$escuela->id})");

            $contexto->ejecutarAislado($escuela->id, function () use (
                $escuela,
                $saldo,
                $hayCaducidad,
                $aplicar
            ): void {
                $this->mostrarSinCaducidad($saldo);

                if ($hayCaducidad) {
                    $this->mostrarVencidas($saldo);

                    if ($aplicar) {
                        VencerRecuperacionesJob::dispatch($escuela->id);
                        $this->line('  Trabajo encolado para marcar las vencidas.');
                    }
                }
            });

            $this->line('');
        }

        if (! $hayCaducidad) {
            $this->line('Configure ESCUELA_MUSICA_DIAS_RECUPERACION si la escuela quiere');
            $this->line('que estas clases caduquen.');
        }

        return self::SUCCESS;
    }

    private function mostrarSinCaducidad(SaldoDeRecuperaciones $saldo): void
    {
        $deudas = $saldo->sinCaducidad();

        if ($deudas->isEmpty()) {
            $this->line('  Deuda sin fecha de vencimiento: ninguna.');

            return;
        }

        $this->line('  Deuda que hay que revisar a mano (sin fecha de vencimiento):');

        foreach ($deudas as $recuperacion) {
            $this->line(sprintf(
                '    - %s | clase: %s | creada: %s',
                $recuperacion->alumno?->nombreCompleto() ?? 'alumno desconocido',
                $recuperacion->claseOrigen?->nombre ?? 'clase desconocida',
                $recuperacion->creada_en->format('d/m/Y')
            ));
        }
    }

    private function mostrarVencidas(SaldoDeRecuperaciones $saldo): void
    {
        $vencidas = $saldo->vencidas();

        if ($vencidas->isEmpty()) {
            $this->line('  Recuperaciones vencidas: ninguna.');

            return;
        }

        $this->line('  Recuperaciones que ya pasaron su fecha:');

        foreach ($vencidas as $recuperacion) {
            $this->line(sprintf(
                '    - %s | vence: %s',
                $recuperacion->alumno?->nombreCompleto() ?? 'alumno desconocido',
                $recuperacion->vence_el?->format('d/m/Y') ?? 'sin fecha'
            ));
        }
    }

    /**
     * @return list<Escuela>
     */
    private function resolveEscuelas(): array
    {
        $filtro = $this->option('escuela');

        $consulta = Escuela::query()->where('activo', true);

        if ($filtro !== null && $filtro !== '') {
            $consulta->where('id', $filtro);
        }

        return $consulta->get()->all();
    }
}