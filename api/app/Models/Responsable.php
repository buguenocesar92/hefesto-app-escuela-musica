<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Responsable: el adulto que responde por un alumno.
 *
 * Son datos personales de una persona mayor. No se exponen en las pantallas de
 * los docentes: el docente necesita saber a quién llamar si el menor no llega,
 * no el historial de esa persona.
 */
class Responsable extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'responsables';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'es_responsable_legal' => 'boolean',
        ];
    }

    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(
            Alumno::class,
            'alumno_responsables',
            'responsable_id',
            'alumno_id'
        )->withPivot([
            'relacion',
            'es_responsable_legal',
            'autorizacion_registrada',
            'autorizacion_registrada_en',
            'autorizacion_vence_el',
        ])->withTimestamps();
    }
}