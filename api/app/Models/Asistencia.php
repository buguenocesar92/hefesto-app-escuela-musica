<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoAsistencia;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asistencia de un alumno a una clase.
 *
 * `consume_clase_plan` es TRI-ESTADO y es lo más importante de esta clase:
 * `true` descontó cupo, `false` no descontó, y NULL es que la regla del negocio
 * no está definida y el sistema no tocó nada. Ver la nota de la migración
 * 2026_10_18_000013.
 */
class Asistencia extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'asistencias';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoAsistencia::class,
            'consume_clase_plan' => 'boolean',
            'registrado_en' => 'datetime',
        ];
    }

    public function clase(): BelongsTo
    {
        return $this->belongsTo(Clase::class, 'clase_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }

    public function esAusencia(): bool
    {
        return $this->estado === EstadoAsistencia::Ausente;
    }

    /**
     * ¿La regla de consumo quedó sin decidir?
     *
     * Es la consulta que responde "¿qué tengo que revisar antes de cerrar el
     * mes?".
     */
    public function reglaSinDefinir(): bool
    {
        return $this->consume_clase_plan === null;
    }
}