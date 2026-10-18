<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Qué representa un pago.
 *
 * La cuota del plan mensual y el pago de una clase suelta viven en la misma
 * tabla para que el estado de cuenta del alumno se arme de una sola consulta.
 * Ver la nota de la migración 2026_10_18_000010.
 */
enum ConceptoPago: string
{
    case CuotaPlan = 'cuota_plan';
    case ClaseSuelta = 'clase_suelta';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CuotaPlan => 'Cuota de plan',
            self::ClaseSuelta => 'Clase suelta',
        };
    }

    /**
     * ¿Un pago con este concepto puede tener una inscripción asociada?
     *
     * Una cuota de plan SÍ se apoya en una inscripción: es su período. Una
     * clase suelta NO: se paga suelta precisamente porque no hay inscripción.
     */
    public function admiteInscripcion(): bool
    {
        return $this === self::CuotaPlan;
    }
}