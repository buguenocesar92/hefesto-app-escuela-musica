<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sala;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sala>
 */
class SalaFactory extends Factory
{
    protected $model = Sala::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Sala ' . fake()->unique()->numberBetween(1, 999),
            'capacidad' => null,
            'activa' => true,
            'observacion' => null,
        ];
    }
}