<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConceptoPago;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pago: cuota de plan o clase suelta.
 *
 * `pagado_en` en NULL es lo que hace a alguien moroso. No hay columna
 * `estado`: el estado se deriva de si está pagado y si está anulado, y derivarlo
 * evita el caso en que las tres cosas se contradigan entre sí.
 */
class Pago extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'pagos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'concepto' => ConceptoPago::class,
            'monto' => 'integer',
            'periodo_desde' => 'date',
            'periodo_hasta' => 'date',
            'pagado_en' => 'datetime',
            'anulado' => 'boolean',
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

    /** ¿Está pendiente de pago? */
    public function estaPendiente(): bool
    {
        return ! $this->anulado && $this->pagado_en === null;
    }
}