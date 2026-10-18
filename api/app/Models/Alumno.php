<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Alumno de la escuela.
 *
 * ATENCIÓN CON LOS DATOS DE MENORES
 * -------------------------------
 * Casi todo lo que hay aquí (nombre, RUT, fecha de nacimiento, dirección) son
 * datos personales. Cuando el alumno es menor de edad, el tratamiento de esos
 * datos depende de una autorización del adulto responsable, que vive en
 * `alumnoResponsables` y no en esta tabla.
 *
 * El método `esMenorEdad()` NO guarda el resultado en una columna: la edad
 * cambia todos los días y una columna se quedaría vieja. Ver docs/06.
 */
class Alumno extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'alumnos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class, 'tenant_id');
    }

    public function responsables(): BelongsToMany
    {
        return $this->belongsToMany(
            Responsable::class,
            'alumno_responsables',
            'alumno_id',
            'responsable_id'
        )->withPivot([
            'relacion',
            'es_responsable_legal',
            'autorizacion_registrada',
            'autorizacion_registrada_en',
            'autorizacion_vence_el',
        ])->withTimestamps();
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'alumno_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'alumno_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'alumno_id');
    }

    public function recuperaciones(): HasMany
    {
        return $this->hasMany(Recuperacion::class, 'alumno_id');
    }

    public function movimientosClasePlan(): HasMany
    {
        return $this->hasMany(MovimientoClasePlan::class, 'alumno_id');
    }

    public function prestamosInstrumento(): HasMany
    {
        return $this->hasMany(PrestamoInstrumento::class, 'alumno_id');
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    /**
     * ¿Es menor de edad a la fecha indicada?
     *
     * Con `fecha_nacimiento` en NULL no se sabe, y el sistema NO adivina.
     * Devuelve false para que la validación del alta lo pida, en vez de
     * asumir que es mayor y saltarse la autorización sin querer.
     */
    public function esMenorEdad(?Carbon $fecha = null): bool
    {
        if ($this->fecha_nacimiento === null) {
            return false;
        }

        $referencia = $fecha ?? Carbon::now();

        // Un alumno que cumple 18 hoy ya no es menor. Se compara contra el
        // cumpleaños, no contra los 18 años exactos de la fecha de referencia.
        return $referencia->lt($this->cumpleAniosEn($referencia));
    }

    /**
     * Fecha en que la persona cumple 18 años.
     *
     * `->addYears()` respeta los 29 de febrero: si nació un 29 de febrero,
     * cumple años el 28 de febrero en años que no tienen ese día, no el 1 de
     * marzo ni el 28 de diciembre.
     */
    private function cumpleAniosEn(Carbon $referencia): Carbon
    {
        return Carbon::create(
            $referencia->year - 18,
            $this->fecha_nacimiento->month,
            $this->fecha_nacimiento->day
        );
    }

    /** ¿Tiene al menos un responsable con autorización registrada? */
    public function tieneAutorizacionRegistrada(): bool
    {
        return $this->responsables()->wherePivot('autorizacion_registrada', true)->exists();
    }
}