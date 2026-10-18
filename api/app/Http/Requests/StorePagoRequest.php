<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConceptoPago;
use Illuminate\Validation\Rule;

/**
 * Registro de un pago: cuota de plan o clase suelta.
 *
 * `monto` es OBLIGATORIO en el formulario aunque la columna admita NULL.
 *
 * La razón es que son dos cosas distintas: la columna admite NULL para un cargo
 * generado por el sistema del que todavía no hay monto (porque el plan no tiene
 * precio definido), pero cuando alguien ANOTA un cobro tiene que decir cuánto
 * cobró. Aceptar un cobro sin monto es aceptar un cobro que no se sabe cuánto
 * vale.
 *
 * `pagado_en` en NULL significa pendiente: es el estado normal de una cuota que
 * se generó al comenzar el mes y todavía no se ha pagado.
 */
class StorePagoRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        if (! $this->requiereSesion()) {
            return true;
        }

        return $this->user()?->vePagos() ?? false;
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
            'alumno_id' => ['required', 'uuid', 'exists:alumnos,id'],
            'inscripcion_id' => ['nullable', 'uuid', 'exists:inscripciones,id'],
            'concepto' => ['required', Rule::in(array_column(ConceptoPago::cases(), 'value'))],
            'monto' => ['required', 'integer', 'min:0'],
            'periodo_desde' => ['nullable', 'date'],
            'periodo_hasta' => ['nullable', 'date', 'after_or_equal:periodo_desde'],
            'pagado_en' => ['nullable', 'date'],
            'metodo' => ['nullable', 'string', 'max:30'],
            'referencia' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto.required' => 'Indique el monto del cobro.',
            'monto.integer' => 'El monto debe ser un número entero de pesos.',
            'alumno_id.exists' => 'El alumno indicado no existe en esta escuela.',
            'concepto.in' => 'El concepto del pago no es válido.',
            'periodo_hasta.after_or_equal' => 'El período no puede terminar antes de empezar.',
        ];
    }
}