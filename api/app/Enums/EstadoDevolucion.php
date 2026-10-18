<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado en que volvió un instrumento prestado o arrendado.
 *
 * Es la respuesta a "¿volvió entero?". Acepta `bueno`, `con_danos` e
 * `incompleto` porque son las tres respuestas que la escuela necesita para
 * decidir qué hacer después. No se agrega ninguna más.
 */
enum EstadoDevolucion: string
{
    case Bueno = 'bueno';
    case ConDanos = 'con_danos';
    case Incompleto = 'incompleto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Bueno => 'Bueno',
            self::ConDanos => 'Con daños',
            self::Incompleto => 'Incompleto',
        };
    }

    /** Un instrumento devuelto con daños no queda disponible: va a mantención. */
    public function dejaDisponible(): bool
    {
        return $this === self::Bueno;
    }
}