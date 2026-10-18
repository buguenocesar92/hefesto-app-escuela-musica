<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Alumno;
use App\Models\Usuario;

/**
 * Quién puede ver y editar un alumno.
 *
 * DOS CAPAS, Y NO ES REDUNDANTE
 * -----------------------------
 * La Row Level Security ya garantiza que el alumno y el usuario son de la misma
 * escuela. Esta política decide lo que la RLS no puede: que un docente vea la
 * lista de alumnos de la escuela (tiene que, para llamar a la clase) pero no la
 * dirección de sus casas.
 *
 * Con una sola de las dos capas el sistema tiene agujeros:
 *  - Solo RLS: un docente vería los datos personales de todos los alumnos.
 *  - Solo la política: un docente podría abrir por URL la ficha de un alumno de
 *    otra escuela.
 *
 * CUANDO ESTA POLÍTICA RECHAZA, LA RESPUESTA ES 404
 * -----------------------------------------------
 * El motivo está en `bootstrap/app.php`: una autorización rechazada devuelve
 * "No encontrado" y no "Prohibido", para no confirmar que ese identificador
 * existe en alguna parte.
 */
class AlumnoPolicy
{
    /** Ver el listado de alumnos. */
    public function verListado(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    /**
     * Ver a ESTE alumno.
     *
     * La RLS ya descartó los alumnos de otras escuelas. Lo que se decide acá es
     * el dato de contacto: recepción y administración lo necesitan para llamar;
     * un docente solo necesita la lista de nombres de su clase.
     */
    public function ver(Usuario $usuario, Alumno $alumno): bool
    {
        if (! $usuario->activo) {
            return false;
        }

        return $usuario->administra()
            || in_array(
                $usuario->rol?->value,
                config('escuela-musica.roles_que_registran_asistencia', []),
                true
            );
    }

    /**
     * Ver la ficha COMPLETA: dirección, teléfono, RUT y fecha de nacimiento.
     *
     * Distinto de `ver()`. El docente que imparte la clase necesita saber quién
     * viene; no necesita la dirección de la casa del menor. Esos datos son de
     * un menor de edad y no se entregan a quien no tiene por qué conocerlos.
     */
    public function verFichaCompleta(Usuario $usuario, Alumno $alumno): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /** Registrar la matrícula de un alumno nuevo. */
    public function crear(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /** Editar la ficha. */
    public function actualizar(Usuario $usuario, Alumno $alumno): bool
    {
        return $usuario->activo && $usuario->administra();
    }
}