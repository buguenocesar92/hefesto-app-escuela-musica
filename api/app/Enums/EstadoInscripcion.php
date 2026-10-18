<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una inscripción de un alumno a un plan.
 */
enum EstadoInscripcion: string
{
    case Activa = 'activa';
    case Suspendida = 'suspendida';
    case Terminada = 'terminada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activa => 'Activa',
            self::Suspendida => 'Suspendida',
            self::Terminada => 'Terminada',
        };
    }

    /**
     * Una inscripción suspendida NO consume clases del plan.
     *
     * Es la lectura más simple de la palabra "suspendida": si alguien pausa su
     * mensualidad, no debería verle gastar el cupo de un mes que no está
     * pagando.
     */
    public function habilitaConsumo(): bool
    {
        return $this === self::Activa;
    }
}