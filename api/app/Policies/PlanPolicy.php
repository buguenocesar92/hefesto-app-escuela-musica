<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plan;
use App\Models\Usuario;

/**
 * Quién puede ver y editar los planes.
 *
 * Un docente puede VER los nombres de los planes (para saber si el alumno tiene
 * mensualidad o paga suelto) pero no puede modificarlos. Cambiar el precio o el
 * cupo de clases es una decisión de la dirección, y el precio se cambia con
 * historial de pagos detrás.
 */
class PlanPolicy
{
    public function verListado(Usuario $usuario): bool
    {
        return $usuario->activo;
    }

    public function ver(Usuario $usuario, Plan $plan): bool
    {
        return $usuario->activo;
    }

    public function crear(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    public function actualizar(Usuario $usuario, Plan $plan): bool
    {
        return $usuario->activo && $usuario->administra();
    }
}