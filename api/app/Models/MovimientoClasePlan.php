<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimientoClasePlan;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento del libro de consumo de clases del plan.
 *
 * Es un libro, no un contador: cada cambio del cupo deja una fila que explica
 * por qué cambió. Ver la nota de la migración 2026_10_18_000015.
 */
class MovimientoClasePlan extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'movimientos_clase_plan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoClasePlan::class,
            'ocurre_en' => 'datetime',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    public function clase(): BelongsTo
    {
        return $this->belongsTo(Clase::class, 'clase_id');
    }

    public function asistencia(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class, 'asistencia_id');
    }
}