<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sala: el espacio físico donde ocurre una clase.
 *
 * Existe como entidad propia porque dos clases distintas pueden disputarse la
 * misma sala a la misma hora. Si la sala fuera un texto dentro de la clase, ese
 * choque no se podría detectar.
 */
class Sala extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'salas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'capacidad' => 'integer',
            'activa' => 'boolean',
        ];
    }

    public function clases(): HasMany
    {
        return $this->hasMany(Clase::class, 'sala_id');
    }
}