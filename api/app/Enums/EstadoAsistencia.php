<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una asistencia.
 *
 * Son dos estados y no más. No hay `justificado`, ni `permiso`, ni
 * `presentado_tarde`: la escuela no ha definido esas categorías y agregar una
 * sería inventar una regla de negocio que después nadie podría cambiar porque
 * quedaría grabada en la historia.
 *
 * Si más adelante la escuela necesita esas categorías, se agregan ACÁ y en la
 * validación de `StoreAsistenciaRequest`, en este mismo orden.
 */
enum EstadoAsistencia: string
{
    case Presente = 'presente';
    case Ausente = 'ausente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Presente => 'Presente',
            self::Ausente => 'Ausente',
        };
    }
}