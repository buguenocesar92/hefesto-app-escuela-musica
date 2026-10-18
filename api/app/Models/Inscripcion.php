<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoInscripcion;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inscripción: un alumno suscrito a un plan.
 *
 * El vínculo entre la persona y el plan que paga. La decisión es POR ALUMNO:
 * dos alumnos en la misma clase pueden estar uno con plan y otro pagando
 * clase por clase.
 */
class Inscripcion extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'inscripciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'iniciada_en' => 'date',
            'terminada_en' => 'date',
            'estado' => EstadoInscripcion::class,
            'precio_congelado' => 'integer',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'inscripcion_id');
    }

    public function movimientosClasePlan(): HasMany
    {
        return $this->hasMany(MovimientoClasePlan::class, 'inscripcion_id');
    }

    /** ¿Esta inscripción habilita hoy a consumir clases del plan? */
    public function habilitaConsumo(): bool
    {
        return $this->estado instanceof EstadoInscripcion
            && $this->estado->habilitaConsumo()
            && $this->terminada_en === null;
    }

    /** El precio que se cobró, o el del plan si no se congeló al contratar. */
    public function precioVigente(): ?int
    {
        return $this->precio_congelado ?? $this->plan?->precio;
    }
}