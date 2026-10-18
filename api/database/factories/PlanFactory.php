<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 *
 * LOS TRES NÚMEROS IMPORTANTES SON NULL POR OMISIÓN
 * -------------------------------------------------
 * `clases_incluidas`, `precio` y `vigencia_dias` salen en NULL, no con un número.
 *
 * Una fábrica que pone un precio de ejemplo produce facturas de ejemplo. Y un
 * plan de prueba con un cupo fijo haría que las pruebas de consumo tomaran un
 * camino que la escuela real nunca tomó.
 *
 * Para probar el camino "plan con cupo definido", hay un estado explícito:
 * `->conCupo(4)`. Que sea a propósito evita que el valor de ejemplo se cuele en
 * la base de desarrollo.
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Plan de prueba ' . fake()->unique()->numberBetween(1, 9999),
            'descripcion' => null,
            'clases_incluidas' => null,
            'precio' => null,
            'vigencia_dias' => null,
            'activo' => true,
        ];
    }

    /** Plan con cupo de clases definido. */
    public function conCupo(int $clases): static
    {
        return $this->state(fn (): array => ['clases_incluidas' => $clases]);
    }

    /** Plan con precio definido. */
    public function conPrecio(int $precio): static
    {
        return $this->state(fn (): array => ['precio' => $precio]);
    }
}