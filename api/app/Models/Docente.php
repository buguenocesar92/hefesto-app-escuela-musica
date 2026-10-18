<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Docente: la persona que imparte clases.
 *
 * NO es lo mismo que `Usuario`. La misma persona puede dar clases sin tener
 * sesión abierta, o tener sesión sin dar clases.
 */
class Docente extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'docentes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function clases(): HasMany
    {
        return $this->hasMany(Clase::class, 'docente_id');
    }
}