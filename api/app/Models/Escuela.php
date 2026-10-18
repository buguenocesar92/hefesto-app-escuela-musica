<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Escuela: la entidad que da nombre al aislamiento.
 *
 * NO usa el trait `PerteneceAEscuela` y NO tiene `tenant_id`: es la tabla TENANT
 * en sí. Se consulta en el selector de ingreso, antes de que exista contexto,
 * y para eso está la vista `vw_escuelas_publicas`.
 */
class Escuela extends Model
{
    use HasUuids;

    protected $table = 'escuelas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}