<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una recuperación.
 *
 * El recorrido normal es `pendiente` -> `agendada` -> `usada`.
 *
 * `vencida` se alcanza SOLO si hay una fecha de vencimiento en la recuperación.
 * Si la escuela no configuró `ESCUELA_MUSICA_DIAS_RECUPERACION`, la
 * recuperación nunca pasa a `vencida`: sigue `pendiente` para siempre. Eso es
 * correcto a propósito, y por eso la deuda sin caducar se muestra aparte: si
 * se dejara en `pendiente` sin más, nadie la vería.
 *
 * `anulada` es para cuando la escuela decide que la deuda no era real (un
 * error de registro, un acuerdo con la familia). No borra nada.
 */
enum EstadoRecuperacion: string
{
    case Pendiente = 'pendiente';
    case Agendada = 'agendada';
    case Usada = 'usada';
    case Vencida = 'vencida';
    case Anulada = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Agendada => 'Agendada',
            self::Usada => 'Usada',
            self::Vencida => 'Vencida',
            self::Anulada => 'Anulada',
        };
    }

    /** ¿Cuenta como deuda que la escuela todavía tiene que entregar? */
    public function esDeuda(): bool
    {
        return $this === self::Pendiente || $this === self::Agendada;
    }
}