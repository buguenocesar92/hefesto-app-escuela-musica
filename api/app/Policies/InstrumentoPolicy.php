<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Alumno;
use App\Models\Instrumento;
use App\Models\Usuario;

/**
 * Quién puede ver el inventario de instrumentos y quién puede tocarlo.
 *
 * VER INVENTARIO
 * -------------
 * Todo el personal activo lo ve, incluido el docente: en una escuela de música
 * el "¿tienen un amplifier?" se pregunta en el pasillo y la respuesta tiene que
 * ser inmediata.
 *
 * TOCAR EL INVENTARIO
 * -------------------
 * Entregar o recibir un instrumento no es un detalle de secretaría: es la
 * operación por la que la escuela puede reclamar. Se deja en administración,
 * con la decisión del cliente de delegarla o no.
 */
class InstrumentoPolicy
{
    public function verListado(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function ver(Usuario $usuario, Instrumento $instrumento): bool
    {
        return $usuario->activo;
    }

    public function crear(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    public function actualizar(Usuario $usuario, Instrumento $instrumento): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /** Entregar un instrumento a un alumno. */
    public function prestar(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /**
     * Registrar la devolución.
     *
     * Se exige el mismo permiso que la entrega: quien entrega debería ser quien
     * recibe, porque es quien puede observar el estado del instrumento.
     */
    public function devolver(Usuario $usuario, Instrumento $instrumento): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /** Ver quién tiene prestado un instrumento sin devolver. */
    public function verPrestamos(Usuario $usuario, Instrumento $instrumento): bool
    {
        return $usuario->activo;
    }

    /** Ver los préstamos de un alumno. */
    public function verPrestamosDeAlumno(Usuario $usuario, Alumno $alumno): bool
    {
        return $usuario->activo;
    }
}