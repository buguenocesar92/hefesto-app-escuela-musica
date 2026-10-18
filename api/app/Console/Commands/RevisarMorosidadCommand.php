<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Asistencia;
use App\Models\Escuela;
use App\Models\Pago;
use App\Services\Tenancy\ContextoEscuela;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Revisa quién quedó moroso y qué falta por definir.
 *
 * POR QUÉ UN COMANDO Y NO SOLO UNA PANTALLA
 * ----------------------------------------
 * La pregunta "¿quién me debe?" se hace mirando el computador, no en un
 * navegador. Este comando entrega la lista para imprimir, y además la lista de
 * ausencias cuya regla de consumo quedó sin decidir.
 *
 * EL AVISO DE VENCIMIENTO ES CONFIGURABLE, Y POR OMISIÓN NO EXISTE
 * ----------------------------------------------------------------
 * `ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD` decide con cuántos días de anticipación
 * se avisa. Sin ese valor, no hay aviso, pero la lista COMPLETA se sigue
 * mostrando. Un aviso inventado sería peor que ninguno: haría creer que hay un
 * plazo acordado con la familia que en realidad nadie acordó.
 */
class RevisarMorosidadCommand extends Command
{
    protected $signature = 'escuela-musica:revisar-morosidad
                            {--escuela= : Limitar a una escuela por su identificador}
                            {--alumno= : Limitar a un alumno por su identificador}';

    protected $description = 'Lista los pagos pendientes, las ausencias por definir y las recuperaciones sin fecha.';

    public function handle(ContextoEscuela $contexto): int
    {
        $diasAviso = $this->diasDeAviso();

        if ($diasAviso === null) {
            $this->warn('Sin ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD no hay ventana de aviso.');
            $this->line('Se muestra igual la lista completa de pagos pendientes.');
            $this->line('');
        }

        $escuelas = Escuela::query()->where('activo', true)->get();

        if ($this->option('escuela') !== null && $this->option('escuela') !== '') {
            $escuelas = $escuelas->where('id', $this->option('escuela'))->values();
        }

        foreach ($escuelas as $escuela) {
            $this->line("Escuela: {$escuela->nombre} ({$escuela->id})");

            $contexto->ejecutarAislado($escuela->id, function () use ($diasAviso): void {
                $this->mostrarPendientes($diasAviso);
                $this->mostrarAusenciasPorDefinir();
            });

            $this->line('');
        }

        return self::SUCCESS;
    }

    private function diasDeAviso(): ?int
    {
        $valor = config('escuela-musica.dias_aviso_morosidad');

        if ($valor === null) {
            return null;
        }

        $dias = (int) $valor;

        return $dias > 0 ? $dias : null;
    }

    private function mostrarPendientes(?int $diasAviso): void
    {
        $consulta = Pago::query()
            ->where('anulado', false)
            ->whereNull('pagado_en')
            ->with('alumno');

        if ($this->option('alumno') !== null && $this->option('alumno') !== '') {
            $consulta->where('alumno_id', $this->option('alumno'));
        }

        $pendientes = $consulta->orderBy('periodo_hasta')->get();

        if ($pendientes->isEmpty()) {
            $this->line('  Pagos pendientes: ninguno.');

            return;
        }

        $this->line('  Pagos pendientes:');

        foreach ($pendientes as $pago) {
            $linea = sprintf(
                '    - %s | %s | monto: %s',
                $pago->alumno?->nombreCompleto() ?? 'alumno desconocido',
                $pago->concepto?->etiqueta() ?? 'concepto desconocido',
                $pago->monto === null ? 'sin monto definido' : '$' . number_format($pago->monto, 0, ',', '.')
            );

            if ($pago->periodo_hasta !== null) {
                $linea .= ' | hasta: ' . $pago->periodo_hasta->format('d/m/Y');
            }

            if ($diasAviso !== null && $pago->periodo_hasta !== null) {
                $diasParaVencer = (int) Carbon::now()->startOfDay()
                    ->diffInDays($pago->periodo_hasta->startOfDay(), false);

                if ($diasParaVencer < 0) {
                    $linea .= ' | VENCIDO hace ' . abs($diasParaVencer) . ' día(s)';
                } elseif ($diasParaVencer <= $diasAviso) {
                    $linea .= ' | vence en ' . $diasParaVencer . ' día(s)';
                }
            }

            $this->line($linea);
        }
    }

    private function mostrarAusenciasPorDefinir(): void
    {
        $consulta = Asistencia::query()
            ->whereNull('consume_clase_plan')
            ->with(['alumno', 'clase']);

        if ($this->option('alumno') !== null && $this->option('alumno') !== '') {
            $consulta->where('alumno_id', $this->option('alumno'));
        }

        $sinDefinir = $consulta->orderBy('registrado_en')->get();

        if ($sinDefinir->isEmpty()) {
            $this->line('  Ausencias por definir: ninguna.');

            return;
        }

        $this->line('  Ausencias cuya regla de consumo quedó sin definir:');

        foreach ($sinDefinir as $asistencia) {
            $this->line(sprintf(
                '    - %s | clase: %s | registrada: %s',
                $asistencia->alumno?->nombreCompleto() ?? 'alumno desconocido',
                $asistencia->clase?->nombre ?? 'clase desconocida',
                $asistencia->registrado_en->format('d/m/Y H:i')
            ));
        }

        $this->line('');
        $this->line('    Configure ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN y decida');
        $this->line('    estas ausencias a mano. El sistema no las toca solo.');
    }
}