<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EstadoAsistencia;
use Illuminate\Validation\Rule;

/**
 * Registro de asistencia de una clase.
 *
 * `estado` es obligatorio y solo admite los dos estados que existen. No hay
 * "justificado" porque la escuela no lo definió.
 */
class StoreAsistenciaRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        if (! $this->requiereSesion()) {
            return true;
        }

        return $this->user()?->registraAsistencia() ?? false;
    }

    public function requiereSesion(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(array_column(EstadoAsistencia::cases(), 'value'))],
            'generar_recuperacion' => ['nullable', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Indique si el alumno estuvo presente o ausente.',
            'estado.in' => 'El estado de asistencia no es válido.',
        ];
    }
}