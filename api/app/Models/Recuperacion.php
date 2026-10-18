<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoRecuperacion;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recuperación: la deuda de clases cuando un alumno falta.
 *
 * `vence_el` en NULL significa que NO hay caducidad configurada, no que la
 * deuda esté vencida. La distinción importa: con la regla definida, el comando
 * `escuela-musica:vencer-recuperaciones` pasa las vencidas a `vencida`; sin
 * ella, quedan `pendiente` para siempre y por eso se listan aparte.
 */
class Recuperacion extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'recuperaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoRecuperacion::class,
            'creada_en' => 'datetime',
            'vence_el' => 'datetime',
            'usada_en' => 'datetime',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function claseOrigen(): BelongsTo
    {
        return $this->belongsTo(Clase::class, 'clase_origen_id');
    }

    /** La clase por la que se recupera. NULL mientras está pendiente. */
    public function claseNueva(): BelongsTo
    {
        return $this->belongsTo(Clase::class, 'clase_nueva_id');
    }

    public function asistenciaOrigen(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class, 'asistencia_origen_id');
    }

    /** ¿Sigue contando como deuda? */
    public function esDeuda(): bool
    {
        return $this->estado instanceof EstadoRecuperacion && $this->estado->esDeuda();
    }

    /**
     * ¿Esta recuperación nunca va a caducar?
     *
     * La escuela no configuró `ESCUELA_MUSICA_DIAS_RECUPERACION`. Una deuda sin
     * fecha no se puede automatizar: hay que revisarla a mano, para siempre.
     */
    public function esSinCaducidad(): bool
    {
        return $this->vence_el === null && $this->esDeuda();
    }

    /** ¿Pasó la fecha de vencimiento y sigue pendiente? */
    public function pasoSuVencimiento(?\Illuminate\Support\Carbon $referencia = null): bool
    {
        if ($this->vence_el === null || ! $this->esDeuda()) {
            return false;
        }

        $referencia ??= now();

        return $referencia->gt($this->vence_el);
    }
}