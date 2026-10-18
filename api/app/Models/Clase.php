<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Clase: un evento concreto en la agenda.
 *
 * "Piano los martes a las 18:00" es un curso; la fila de esta tabla es la clase
 * del martes 3, de 18:00 a 19:00, con un docente y en una sala.
 *
 * El choque de horario se comprueba en
 * `App\Services\Agenda\DetectorDeSolapamiento`. La base no lo impide: no hay
 * ningún índice de PostgreSQL que exprese "estos intervalos se pisan".
 */
class Clase extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'clases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'termina' => 'datetime',
            'capacidad' => 'integer',
        ];
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(
            Alumno::class,
            'inscripciones_clase',
            'clase_id',
            'alumno_id'
        )->withTimestamps();
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'clase_id');
    }

    /**
     * ¿Está anulada?
     *
     * Una clase anulada se conserva: tiene asistencias y pagos asociados. Por
     * eso el estado es una columna y no una fila borrada.
     */
    public function estaAnulada(): bool
    {
        return $this->estado === 'anulada';
    }
}