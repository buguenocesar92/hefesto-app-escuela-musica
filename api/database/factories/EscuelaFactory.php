<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Escuela;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Escuela>
 */
class EscuelaFactory extends Factory
{
    protected $model = Escuela::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = 'Escuela de prueba ' . Str::upper(Str::random(6));

        return [
            'slug' => Str::slug($nombre),
            'nombre' => $nombre,
            // Sin razón social y sin RUT: se inventarían. Se rellenan solo si
            // la escuela realmente emite factura.
            'razon_social' => null,
            'rut' => null,
            'activo' => true,
        ];
    }

    /** Escuela inactiva: no debe aparecer en el selector de ingreso. */
    public function inactiva(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}