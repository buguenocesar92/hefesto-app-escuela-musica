<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de movimiento en el libro de consumo de clases del plan.
 *
 * `Consumo` y `Devolucion` son simétricos: lo que entra por consumo sale por
 * devolución. `Ajuste` es la excepción que la administración usa para
 * corregir, y exige motivo (restricción CHECK en la base).
 *
 * La restricción está en la migración 2026_10_18_000015, no acá: una
 * enumeración en PHP no se puede esquivar desde la consola de PostgreSQL.
 */
enum TipoMovimientoClasePlan: string
{
    case Consumo = 'consumo';
    case Devolucion = 'devolucion';
    case Ajuste = 'ajuste';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Consumo => 'Consumo',
            self::Devolucion => 'Devolución',
            self::Ajuste => 'Ajuste',
        };
    }

    /** Efecto sobre el cupo disponible. */
    public function sumaAlSaldo(): int
    {
        return match ($this) {
            self::Consumo => -1,
            self::Devolucion => 1,
            // Un ajuste no mueve el cupo: es una corrección documental.
            self::Ajuste => 0,
        };
    }
}