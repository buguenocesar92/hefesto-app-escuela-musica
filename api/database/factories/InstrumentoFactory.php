<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoInstrumento;
use App\Models\Instrumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instrumento>
 */
class InstrumentoFactory extends Factory
{
    protected $model = Instrumento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Instrumento de prueba ' . fake()->unique()->numberBetween(1, 9999),
            'tipo' => fake()->randomElement(['Cordófono', 'Tecla', 'Percusión', 'Viento']),
            'estado' => EstadoInstrumento::Disponible->value,
            'marca' => null,
            'codigo_interno' => null,
            'observacion' => null,
        ];
    }

    public function enMantencion(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoInstrumento::Mantencion->value]);
    }

    public function deBaja(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoInstrumento::Baja->value]);
    }
}