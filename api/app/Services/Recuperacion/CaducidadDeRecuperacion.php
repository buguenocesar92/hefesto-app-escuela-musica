<?php

declare(strict_types=1);

namespace App\Services\Recuperacion;

use Illuminate\Support\Carbon;

/**
 * Cuándo caduca una clase recuperada.
 *
 * SI NO HAY PLAZO, NO HAY CADUCIDAD
 * ---------------------------------
 * La vigencia sale de `ESCUELA_MUSICA_DIAS_RECUPERACION`. Sin ese valor, el
 * sistema NO le pone un plazo por su cuenta y `vence_el` queda en NULL.
 *
 * Es tentador poner 90 días, que es el número que aparece en la mayoría de los
 * reglamentos internos. No se hace: es un número inventado con consecuencias
 * reales, porque caduca una deuda que la escuela le debe a una familia.
 *
 * Ver docs/05-CONFIGURACION.md.
 */
class CaducidadDeRecuperacion
{
    /**
     * ¿La escuela configuró un plazo de caducidad?
     */
    public function hayCaducidadConfigurada(): bool
    {
        return $this->dias() !== null;
    }

    /**
     * Días de vigencia configurados, o NULL si no hay.
     */
    public function dias(): ?int
    {
        $valor = config('escuela-musica.dias_vigencia_recuperacion');

        if ($valor === null) {
            return null;
        }

        $dias = (int) $valor;

        // Un plazo de cero o negativo no significa "caduca ya": es un valor mal
        // puesto. Se trata como no configurado.
        return $dias > 0 ? $dias : null;
    }

    /**
     * Fecha de vencimiento de una recuperación creada en `$desde`.
     *
     * @return Carbon|null NULL cuando no hay caducidad configurada.
     */
    public function calculaVence(Carbon $desde): ?Carbon
    {
        $dias = $this->dias();

        if ($dias === null) {
            return null;
        }

        // Se cuenta en días completos desde la clase perdida. Una recuperación
        // creada a las 18:30 no vence antes que una creada a las 9:00 del mismo
        // día solo por el horario.
        return $desde->copy()->addDays($dias)->endOfDay();
    }
}