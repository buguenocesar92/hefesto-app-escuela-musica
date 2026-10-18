<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Alumno;
use App\Models\Escuela;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoEscuela;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Base de las pruebas de la Escuela de Música y Arte.
 *
 * LO IMPORTANTE DE ESTA CLASE
 * --------------------------
 * El aislamiento entre escuelas está implementado con Row Level Security. Si
 * una prueba no fija el contexto de escuela, la política RLS no tiene contra qué
 * filtrar y la prueba pasa sin comprobar nada.
 *
 * Eso es peor que no tener prueba: es una prueba que da verde mientras no
 * comprueba lo que dice comprobar. Por eso `comoUsuario()` hace LAS DOS COSAS:
 * autentica y fija el contexto.
 *
 * Se sigue exactamente el patrón de `apps/colegio/api/tests/TestCase.php`. No
 * se inventó una forma nueva.
 */
abstract class TestCase extends BaseTestCase
{
    /** Escuela fijada para la prueba en curso. */
    protected ?string $escuelaDePrueba = null;

    /**
     * Autentica a una persona y fija su contexto de escuela en la base.
     */
    protected function comoUsuario(Usuario $usuario): static
    {
        $this->actingAs($usuario);

        return $this->fijarEscuela($usuario->tenant_id);
    }

    /**
     * Emula lo que hace `App\Http\Middleware\ResolverTenant`.
     *
     * Se usa `set_config(..., false)`, es decir con alcance de SESIÓN y no de
     * transacción. En la aplicación real es al revés (alcance de transacción,
     * para que el worker de colas no arrastre la escuela de otra). En las
     * pruebas cada caso abre su propia aplicación y su propia conexión, así que
     * el valor no sobrevive entre pruebas: por eso el alcance de sesión es
     * seguro acá y hace falta para que las aserciones, que corren fuera de la
     * transacción del middleware, sigan viendo el mismo contexto.
     *
     * ESTO NO SE VALIDÓ EN EJECUCIÓN. Es una decisión razonada que puede
     * necesitar ajuste cuando las pruebas corran de verdad. Ver docs/10.
     */
    protected function fijarEscuela(?string $escuelaId): static
    {
        if ($escuelaId === null) {
            return $this;
        }

        DB::select('SELECT set_config(?, ?, false)', [ContextoEscuela::VARIABLE, $escuelaId]);

        $this->escuelaDePrueba = $escuelaId;

        return $this;
    }

    /**
     * Ejecuta algo dentro del contexto de una escuela, en una transacción.
     *
     * A diferencia de `fijarEscuela()`, esta sí usa alcance de TRANSACCIÓN, que
     * es el comportamiento real de la aplicación. Sirve para probar que el
     * contexto no sobrevive a la transacción.
     *
     * @template T
     *
     * @param  \Closure(): T  $operacion
     * @return T
     */
    protected function enEscuela(string $escuelaId, \Closure $operacion): mixed
    {
        return app(ContextoEscuela::class)->ejecutarAislado($escuelaId, $operacion);
    }

    /**
     * Crea una escuela con su usuario de dirección y entra a su contexto.
     *
     * @return array{escuela: Escuela, usuario: Usuario}
     */
    protected function crearEscuela(string $slug = 'escuela-prueba.cl'): array
    {
        $escuela = Escuela::factory()->create(['slug' => $slug]);

        $this->fijarEscuela($escuela->id);

        $usuario = Usuario::factory()->direccion()->create([
            'tenant_id' => $escuela->id,
        ]);

        return ['escuela' => $escuela, 'usuario' => $usuario];
    }

    /**
     * Crea un alumno dentro del contexto actual.
     */
    protected function crearAlumno(array $atributos = []): Alumno
    {
        if ($this->escuelaDePrueba === null) {
            throw new \RuntimeException(
                'La prueba no fijó ninguna escuela. Llame antes a crearEscuela().'
            );
        }

        return Alumno::factory()->create($atributos + ['tenant_id' => $this->escuelaDePrueba]);
    }
}