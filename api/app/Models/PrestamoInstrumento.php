<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDevolucion;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Préstamo o arriendo de un instrumento.
 *
 * Guarda en qué estado volvió, porque esa es la pregunta que sirve para
 * reclamar cuando algo falta o llega roto.
 */
class PrestamoInstrumento extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'prestamos_instrumento';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entregado_en' => 'datetime',
            'devuelto_en' => 'datetime',
            'estado_devolucion' => EstadoDevolucion::class,
        ];
    }

    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(Instrumento::class, 'instrumento_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /** ¿El instrumento todavía no volvió? */
    public function estaAbierto(): bool
    {
        return $this->devuelto_en === null;
    }
}