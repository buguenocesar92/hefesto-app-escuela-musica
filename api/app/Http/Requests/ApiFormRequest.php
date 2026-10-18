<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las peticiones de la API.
 *
 * Se usa como base y no directamente: cada petición concreta decide si exige
 * sesión. La diferencia importa porque el ingreso y la salud son las únicas
 * rutas abiertas, y conviene que estén escritas una por una y no heredadas de
 * un "sí, por omisión".
 */
abstract class ApiFormRequest extends FormRequest
{
    /** ¿La petición exige sesión iniciada? */
    abstract public function requiereSesion(): bool;

    public function authorize(): bool
    {
        if (! $this->requiereSesion()) {
            return true;
        }

        return $this->user() !== null;
    }

    /**
     * Formato de error común de la API.
     *
     * En JSON, para que la interfaz pueda mostrar el mensaje al lado del campo
     * en lugar de una pantalla de error genérica.
     */
    protected function failedValidation(\Illuminate\Validation\Validator $validator): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'mensaje' => 'Revise los datos ingresados.',
                'errores' => $validator->errors(),
            ], 422)
        );
    }
}