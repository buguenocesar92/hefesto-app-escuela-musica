<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // `tenant_id` lo pone siempre quien crea el dato. Ver la nota de
            // `AlumnoFactory`: una fábrica que genera un tenant_id propio haría
            // más fácil crear filas en la escuela equivocada.
            'proveedor' => 'authentik',
            'proveedor_identificador' => Str::uuid()->toString(),
            'correo' => fake()->unique()->safeEmail(),
            'nombre_completo' => fake()->name(),
            'rol' => RolUsuario::Docente->value,
            'docente_id' => null,
            'activo' => true,
            'ultimo_acceso' => null,
        ];
    }

    public function direccion(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Direccion->value]);
    }

    public function administracion(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Administracion->value]);
    }

    public function recepcion(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Recepcion->value]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}