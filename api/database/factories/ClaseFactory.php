<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Clase>
 */
class ClaseFactory extends Factory
{
    protected $model = Clase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = Carbon::now()->addDay()->setTime(18, 0);

        return [
            'nombre' => 'Clase de prueba',
            'inicio' => $inicio,
            'termina' => $inicio->copy()->addHour(),
            'capacidad' => null,
            'estado' => 'agendada',
            'notas' => null,
        ];
    }

    /** Clase anulada: no cuenta para la comprobación de solapamiento. */
    public function anulada(): static
    {
        return $this->state(fn (): array => ['estado' => 'anulada']);
    }
}