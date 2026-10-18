<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConceptoPago;
use App\Models\Pago;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = Carbon::now()->startOfMonth();

        return [
            'concepto' => ConceptoPago::CuotaPlan->value,
            // El monto entra en NULL a propósito: el precio lo define la
            // escuela. Para probar el camino con monto, hay un estado
            // explícito: `->conMonto(1)`.
            'monto' => null,
            'periodo_desde' => $inicio->toDateString(),
            'periodo_hasta' => $inicio->copy()->addDays(29)->toDateString(),
            // Pendiente de pago: es el estado normal de una cuota recién
            // generada.
            'pagado_en' => null,
            'metodo' => null,
            'referencia' => null,
            'anulado' => false,
        ];
    }

    public function conMonto(int $monto): static
    {
        return $this->state(fn (): array => ['monto' => $monto]);
    }

    public function pagado(): static
    {
        return $this->state(fn (): array => ['pagado_en' => Carbon::now()]);
    }

    public function claseSuelta(): static
    {
        return $this->state(fn (): array => [
            'concepto' => ConceptoPago::ClaseSuelta->value,
            // Una clase suelta no cubre un período mensual: se paga suelta
            // precisamente porque no hay inscripción.
            'periodo_desde' => null,
            'periodo_hasta' => null,
        ]);
    }

    public function anulado(): static
    {
        return $this->state(fn (): array => ['anulado' => true]);
    }
}