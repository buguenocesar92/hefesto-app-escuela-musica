<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Docente>
 */
class DocenteFactory extends Factory
{
    protected $model = Docente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_completo' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'especialidad' => fake()->randomElement(['Piano', 'Violín', 'Guitarra', 'Canto', 'Dibujo']),
            'activo' => true,
        ];
    }
}