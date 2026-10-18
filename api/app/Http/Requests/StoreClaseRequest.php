<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Alta de una clase en la agenda.
 *
 * `docente_id` y `sala_id` son obligatorios porque una clase sin docente ni sala
 * no se puede impartir: es exactamente el problema que la agenda existe para
 * evitar.
 *
 * El solapamiento NO se comprueba aquí sino en el servicio, porque depende de lo
 * que ya está en la base y no solo de lo que viene en el formulario. Ver
 * `App\Services\Agenda\DetectorDeSolapamiento`.
 */
class StoreClaseRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        if (! $this->requiereSesion()) {
            return true;
        }

        return $this->user()?->can('crear', \App\Models\Clase::class) ?? false;
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
            'nombre' => ['required', 'string', 'max:160'],
            'docente_id' => ['required', 'uuid', 'exists:docentes,id'],
            'sala_id' => ['required', 'uuid', 'exists:salas,id'],
            'inicio' => ['required', 'date'],
            'termina' => ['required', 'date', 'after:inicio'],
            'capacidad' => ['nullable', 'integer', 'min:1'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'docente_id.required' => 'Una clase necesita un docente.',
            'sala_id.required' => 'Una clase necesita una sala.',
            'termina.after' => 'La hora de término debe ser posterior a la de inicio.',
            'docente_id.exists' => 'El docente indicado no existe en esta escuela.',
            'sala_id.exists' => 'La sala indicada no existe en esta escuela.',
        ];
    }
}