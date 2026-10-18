<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Alumno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    protected $model = Alumno::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'rut' => $this->rut(),
            'fecha_nacimiento' => fake()->dateTimeBetween('-16 years', '-6 years'),
            'direccion' => fake()->streetAddress() . ', ' . fake()->city(),
            'telefono' => '+56 9 ' . fake()->numerify('#### ####'),
            'correo' => null,
            'fecha_ingreso' => fake()->dateTimeBetween('-5 years', 'now'),
            'activo' => true,
            'observacion' => null,
        ];
    }

    /**
     * Alumno con fecha de nacimiento nula.
     *
     * `esMenorEdad()` devuelve false con la fecha vacía: el sistema no sabe si
     * es menor y no adivina. Esta fábrica existe para probar justo ese caso.
     */
    public function sinFechaNacimiento(): static
    {
        return $this->state(fn (): array => ['fecha_nacimiento' => null]);
    }

    public function adulto(): static
    {
        return $this->state(fn (): array => [
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-25 years'),
        ]);
    }

    /** RUT chileno sintácticamente válido, con dígito verificador. */
    private function rut(): string
    {
        $cuerpo = fake()->unique()->numerify('########');
        $dv = $this->digitoVerificador($cuerpo);

        return substr($cuerpo, 0, 2) . '.' . substr($cuerpo, 2, 3) . '.' . substr($cuerpo, 5, 3) . '-' . $dv;
    }

    private function digitoVerificador(string $cuerpo): int|string
    {
        $suma = 0;

        for ($i = 0; $i < strlen($cuerpo); $i++) {
            $suma += ((int) $cuerpo[$i]) * (2 + ($i % 7));
        }

        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11 => 0,
            10 => 'K',
            default => $resto,
        };
    }
}