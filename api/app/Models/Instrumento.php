<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoInstrumento;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instrumento: lo que la escuela presta o arrienda.
 */
class Instrumento extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'instrumentos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoInstrumento::class,
        ];
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(PrestamoInstrumento::class, 'instrumento_id');
    }

    /** ¿Está disponible para entregarlo ahora? */
    public function estaDisponible(): bool
    {
        return $this->estado instanceof EstadoInstrumento && $this->estado->disponible();
    }

    /**
     * Préstamos abiertos de este instrumento.
     *
     * "Abierto" es `devuelto_en` en NULL. Un instrumento devuelto con daños
     * queda registrado igual: el préstamo no se borra.
     */
    public function prestamosAbiertos(): HasMany
    {
        return $this->prestamos()->whereNull('devuelto_en');
    }
}