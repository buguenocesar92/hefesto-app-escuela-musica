<?php

declare(strict_types=1);

namespace App\Services\Planes;

/**
 * La regla que decide si una ausencia consume la clase del plan.
 *
 * NO HAY VALOR POR OMISIÓN, Y ESO ES LO IMPORTANTE
 * ------------------------------------------------
 * "La ausencia consume la clase" y "la ausencia no consume la clase" son las dos
 * respuestas defendibles de una escuela de música, y cada una cambia el ingreso
 * y la relación con la familia. Elegir una por intuición del programador sería
 * decidir por el cliente algo que es suyo.
 *
 * Por eso `definida()` devuelve false cuando la variable no está configurada, y
 * `consume()` devuelve NULL en vez de un booleano inventado. Quien llama tiene
 * que resolver ese NULL explícitamente.
 *
 * Configuración: `ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN` (`si` o `no`), leída
 * en `config/escuela-musica.php`.
 */
class ReglaDeAusencia
{
    /**
     * ¿La escuela ya decidió esta regla?
     *
     * Con esto en false, la asistencia se registra pero queda marcada como
     * pendiente: nada se consume y nada se inventa.
     */
    public function definida(): bool
    {
        return $this->consume() !== null;
    }

    /**
     * ¿La ausencia consume la clase del plan?
     *
     * @return bool|null TRUE consume, FALSE no consume, NULL sin definir.
     */
    public function consume(): ?bool
    {
        $valor = config('escuela-musica.ausencia_consume_plan');

        if (! is_string($valor)) {
            return null;
        }

        return match (strtolower(trim($valor))) {
            'si', 'sí', 'true', '1' => true,
            'no', 'false', '0' => false,
            // Cualquier otra cosa es un valor mal puesto, no un "sí" ni un
            // "no". Se trata como indefinido, que es lo seguro.
            default => null,
        };
    }

    /** Texto para mostrar al operador por qué no se consumió nada. */
    public function explicacion(): string
    {
        return match ($this->consume()) {
            true => 'La regla configurada indica que la ausencia consume la clase del plan.',
            false => 'La regla configurada indica que la ausencia NO consume la clase del plan.',
            default => 'La regla de consumo por ausencia no está configurada. '
                . 'No se consumió ninguna clase y la ausencia quedó marcada como pendiente. '
                . 'Configure ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN.',
        };
    }
}