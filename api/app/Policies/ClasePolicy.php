<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Clase;
use App\Models\Usuario;

/**
 * Quién puede ver y modificar la agenda.
 *
 * Un docente ve toda la agenda de la escuela: sin eso no sabría que otra clase
 * ocupa la sala que necesita, ni quién lo busca a la hora de la clase.
 *
 * La agenda no tiene datos sensibles de por sí, así que la política es
 * deliberadamente más abierta que la de alumnos. Lo que sí tiene es el nombre
 * del alumno y la hora: ver la lista de una clase ajena no expone la dirección
 * de nadie.
 */
class ClasePolicy
{
    public function verListado(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function ver(Usuario $usuario, Clase $clase): bool
    {
        return $usuario->activo;
    }

    /** Crear una clase. */
    public function crear(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    public function actualizar(Usuario $usuario, Clase $clase): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    public function eliminar(Usuario $usuario, Clase $clase): bool
    {
        return $usuario->activo && $usuario->administra();
    }
}