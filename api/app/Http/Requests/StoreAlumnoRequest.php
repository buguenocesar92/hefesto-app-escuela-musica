<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Alta y edición de un alumno.
 *
 * LO QUE ESTÁ OBLIGATORIO Y LO QUE NO
 * ----------------------------------
 * Solo nombre y apellido son obligatorios. El RUT, la fecha de nacimiento y la
 * dirección se aceptan vacíos, porque una familia puede empezar clases antes de
 * entregar el documento, y bloquear el alta por eso produce el resultado
 * contrario: la escuela anotando al menor en un cuaderno al lado.
 *
 * La fecha de nacimiento sí se pide cuando el alumno va a quedar con datos
 * personales en el sistema, porque de ella depende saber si es menor. Se pide
 * en el servicio de alta, no aquí: ver `AlumnoPolicy` y la nota de `Alumno`.
 */
class StoreAlumnoRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        if (! $this->requiereSesion()) {
            return true;
        }

        return $this->user()?->can('crear', \App\Models\Alumno::class) ?? false;
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
            'nombre' => ['required', 'string', 'max:120'],
            'apellido' => ['required', 'string', 'max:120'],
            'rut' => ['nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'direccion' => ['nullable', 'string', 'max:240'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'correo' => ['nullable', 'email', 'max:160'],
            'fecha_ingreso' => ['nullable', 'date'],
            'activo' => ['nullable', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del alumno es obligatorio.',
            'apellido.required' => 'El apellido del alumno es obligatorio.',
            'rut.max' => 'El RUT no puede tener más de 20 caracteres.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento no es una fecha válida.',
        ];
    }
}