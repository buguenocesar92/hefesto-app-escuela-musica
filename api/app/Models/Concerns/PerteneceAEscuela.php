<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Tenancy\ContextoEscuela;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Marca a los modelos cuyas filas pertenecen a una escuela.
 *
 * La tabla `escuelas` NO usa este trait: se consulta antes de que exista
 * contexto de escuela (durante el inicio de sesión), y es justamente la
 * excepción que permite resolver a qué escuela pertenece la identidad.
 *
 * El trait completa `tenant_id` con el contexto de la conexión actual cuando
 * no se entrega explícitamente. No es la protección: la protección es la Row
 * Level Security, que con `WITH CHECK` rechaza cualquier fila con un
 * `tenant_id` distinto al contexto. Esto solo evita el error de traducción.
 *
 * @mixin Model
 */
trait PerteneceAEscuela
{
    public static function bootPerteneceAEscuela(): void
    {
        static::creating(function (Model $modelo): void {
            if ($modelo->getAttribute('tenant_id') !== null) {
                return;
            }

            $modelo->setAttribute('tenant_id', app(ContextoEscuela::class)->escuelaIdEnConexion());
        });
    }

    /** Un alumno, una clase o un pago de OTRA escuela no existe: 404, no 403. */
    public function scopeDeLaEscuela(Builder $consulta, string $tenantId): Builder
    {
        return $consulta->where('tenant_id', $tenantId);
    }

    public function esDeLaEscuela(string $tenantId): bool
    {
        return $this->getAttribute('tenant_id') === $tenantId;
    }
}