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
 * Plan mensual: el paquete que compra un alumno.
 *
 * Ver la nota de la migración 2026_10_18_000008 sobre por qué `clases_incluidas`,
 * `precio` y `vigencia_dias` admiten NULL. En resumen: un valor inventado
 * termina en una factura.
 */
class Plan extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'planes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'clases_incluidas' => 'integer',
            'precio' => 'integer',
            'vigencia_dias' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'plan_id');
    }

    /** Inscripciones vivas de este plan. */
    public function inscripcionesActivas(): HasMany
    {
        return $this->inscripciones()->where('estado', EstadoInscripcion::Activa->value);
    }

    /**
     * ¿El plan define cuántas clases incluye?
     *
     * Si devuelve false, el sistema NO descuenta nada del cupo. Preferimos no
     * descontar antes que descontar una cantidad que nadie definió.
     */
    public function tieneCupoDefinido(): bool
    {
        return $this->clases_incluidas !== null && $this->clases_incluidas > 0;
    }

    /** ¿El plan tiene un precio definido? */
    public function tienePrecioDefinido(): bool
    {
        return $this->precio !== null;
    }
}