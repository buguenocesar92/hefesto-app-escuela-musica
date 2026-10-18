<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de un instrumento DENTRO de la escuela.
 *
 * El estado en que vuelve de manos de un alumno no vive acá: vive en el
 * préstamo (`estado_devolucion`). Un piano que vuelve con una tecla hundida
 * sigue siendo de la escuela y necesita reparación.
 */
enum EstadoInstrumento: string
{
    case Disponible = 'disponible';
    case Prestado = 'prestado';
    case Arrendado = 'arrendado';
    case Mantencion = 'mantencion';
    case Baja = 'baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Prestado => 'Prestado',
            self::Arrendado => 'Arrendado',
            self::Mantencion => 'En mantención',
            self::Baja => 'De baja',
        };
    }

    /** ¿Se puede ofrecer a un alumno? */
    public function disponible(): bool
    {
        return $this === self::Disponible;
    }
}