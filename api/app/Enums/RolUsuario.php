<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rol de una persona dentro de la escuela.
 *
 * Los valores son TEXTO en la base, no un tipo enumerado de PostgreSQL. Es a
 * propósito: cambiar el conjunto de roles tiene que ser posible desplegando
 * código, no una migración que bloquee la tabla mientras corre.
 *
 * La lista de quién administra y quién registra asistencia está en
 * `config/escuela-musica.php`, no en el enum. El enum solo describe los roles
 * que existen; el config dice qué puede hacer cada uno.
 */
enum RolUsuario: string
{
    case Direccion = 'direccion';
    case Administracion = 'administracion';
    case Docente = 'docente';
    case Recepcion = 'recepcion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Direccion => 'Dirección',
            self::Administracion => 'Administración',
            self::Docente => 'Docente',
            self::Recepcion => 'Recepción',
        };
    }
}