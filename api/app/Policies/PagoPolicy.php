<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Alumno;
use App\Models\Pago;
use App\Models\Usuario;

/**
 * Quién puede ver los pagos.
 *
 * ESTA ES LA POLÍTICA MÁS ESTRICTA DE LA APLICACIÓN
 * -------------------------------------------------
 * Un pago dice cuánto paga una familia y en qué estado está. No es un dato que
 * un docente necesite para impartir una clase, así que la lista de roles que lo
 * ven es explícita (`ESCUELA_MUSICA_ROLES_QUE_VEN_PAGOS`) y no "todos los que
 * pueden entrar".
 *
 * Cuando el rol no está en la lista, la respuesta es 404, igual que en las
 * demás políticas: confirmar que "existen pagos de este alumno" ya confirma algo.
 */
class PagoPolicy
{
    /** Ver el estado de cuenta de un alumno. */
    public function ver(Usuario $usuario, Alumno $alumno): bool
    {
        return $usuario->activo && $usuario->vePagos();
    }

    /** Ver el listado de pagos de la escuela. */
    public function verListado(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->vePagos();
    }

    /** Registrar un cobro. */
    public function crear(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->vePagos();
    }

    /** Anular un cobro mal hecho. */
    public function anular(Usuario $usuario): bool
    {
        return $usuario->activo && $usuario->administra();
    }

    /**
     * Registrar que un pago se hizo efectivo.
     *
     * Lo anotan recepción o administración, no la dirección: quien recibe el
     * dinero es quien sabe que se pagó.
     */
    public function marcarPagado(Usuario $usuario, Pago $pago): bool
    {
        return $usuario->activo && $usuario->vePagos();
    }
}