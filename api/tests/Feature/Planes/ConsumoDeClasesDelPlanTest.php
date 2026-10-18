<?php

declare(strict_types=1);

namespace Tests\Feature\Planes;

use App\Enums\EstadoAsistencia;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Clase;
use App\Models\Inscripcion;
use App\Models\Plan;
use App\Services\Planes\ConsumidorDeClases;
use App\Services\Planes\ReglaDeAusencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consumo de clases del plan.
 *
 * ESTA SUITE ES LA QUE PROTEGE LA DIFERENCIA ENTRE INGRESO ESTABLE Y VARIABLE
 * ---------------------------------------------------------------------------
 * Que alguien con plan vea sus clases y alguien sin plan pague suelto. Y que
 * una ausencia consuma o no, según lo que la escuela decidió.
 *
 * LAS TRES REGLAS NO TIENEN VALOR POR OMISIÓN
 * -------------------------------------------
 * En `phpunit.xml` esas variables están VACÍAS a propósito. Las pruebas que
 * necesitan una regla definida la fijan dentro del caso con `config()->set()`,
 * y las que necesitan el estado "sin definir" la dejan vacía.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. Ver docs/10.
 */
class ConsumoDeClasesDelPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_alumno_con_plan_consume_una_clase_cada_vez_que_asiste(): void
    {
        $escuela = $this->crearEscuela('planes-consumo.cl');
        $alumno = $this->crearAlumno();

        $plan = $this->crearPlanConCupo(4);
        $this->inscribir($alumno, $plan, 4);

        $consumidor = app(ConsumidorDeClases::class);

        $clase = $this->crearClase($escuela['escuela']->id);

        $consumidor->registrar($alumno, $clase->id, EstadoAsistencia::Presente);

        $this->assertSame(1, $consumidor->consumidas($alumno));
        $this->assertSame(3, $consumidor->cupoRestante($alumno));
    }

    public function test_un_alumno_sin_plan_no_tiene_cupo_ni_movimientos(): void
    {
        $this->crearEscuela('planes-sin-plan.cl');
        $alumno = $this->crearAlumno();

        $escuelaId = $this->escuelaDePrueba;
        $clase = $this->crearClase($escuelaId);

        $consumidor = app(ConsumidorDeClases::class);
        $consumidor->registrar($alumno, $clase->id, EstadoAsistencia::Presente);

        // No hay inscripción: no hay cupo que tocar y no se genera movimiento.
        $this->assertNull($consumidor->cupoRestante($alumno));
        $this->assertSame(0, $consumidor->consumidas($alumno));
    }

    /**
     * EL CASO QUE DA NOMBRE A ESTA SUITE
     *
     * Sin la regla configurada, la ausencia NO consume nada y queda marcada
     * como pendiente. El sistema no elige.
     */
    public function test_sin_regla_configurada_la_ausencia_no_consume_y_queda_pendiente(): void
    {
        $escuela = $this->crearEscuela('planes-sin-regla.cl');
        $alumno = $this->crearAlumno();

        $plan = $this->crearPlanConCupo(4);
        $this->inscribir($alumno, $plan, 4);

        // Se asegura de que NO hay regla, aunque el entorno la trajera puesta.
        config()->set('escuela-musica.ausencia_consume_plan', null);

        $consumidor = app(ConsumidorDeClases::class);
        $clase = $this->crearClase($escuela['escuela']->id);

        $asistencia = $consumidor->registrar($alumno, $clase->id, EstadoAsistencia::Ausente);

        $this->assertNull(
            $asistencia->consume_clase_plan,
            'Sin regla configurada, la asistencia debe quedar en NULL, no en false.'
        );

        $this->assertSame(0, $consumidor->consumidas($alumno));
        $this->assertSame(4, $consumidor->cupoRestante($alumno));
    }

    public function test_con_la_regla_en_que_consume_la_ausencia_resta_del_cupo(): void
    {
        $escuela = $this->crearEscuela('planes-consume.cl');
        $alumno = $this->crearAlumno();

        $plan = $this->crearPlanConCupo(4);
        $this->inscribir($alumno, $plan, 4);

        config()->set('escuela-musica.ausencia_consume_plan', 'si');

        $consumidor = app(ConsumidorDeClases::class);
        $clase = $this->crearClase($escuela['escuela']->id);

        $asistencia = $consumidor->registrar($alumno, $clase->id, EstadoAsistencia::Ausente);

        $this->assertTrue($asistencia->consume_clase_plan);
        $this->assertSame(1, $consumidor->consumidas($alumno));
        $this->assertSame(3, $consumidor->cupoRestante($alumno));
    }

    public function test_con_la_regla_en_que_no_consume_la_ausencia_no_resta(): void
    {
        $escuela = $this->crearEscuela('planes-no-consume.cl');
        $alumno = $this->crearAlumno();

        $plan = $this->crearPlanConCupo(4);
        $this->inscribir($alumno, $plan, 4);

        config()->set('escuela-musica.ausencia_consume_plan', 'no');

        $consumidor = app(ConsumidorDeClases::class);
        $clase = $this->crearClase($escuela['escuela']->id);

        $asistencia = $consumidor->registrar($alumno, $clase->id, EstadoAsistencia::Ausente);

        $this->assertFalse($asistencia->consume_clase_plan);
        $this->assertSame(0, $consumidor->consumidas($alumno));
        $this->assertSame(4, $consumidor->cupoRestante($alumno));
    }

    /**
     * Un plan sin cupo definido no se puede descontar.
     *
     * El sistema no inventa un cero: devolvería el saldo en 0 y eso sería
     * afirmar que el alumno no tiene nada.
     */
    public function test_un_plan_sin_cupo_definido_no_descuenta(): void
    {
        $escuela = $this->crearEscuela('planes-sin-cupo.cl');
        $alumno = $this->crearAlumno();

        $plan = $this->fijarEscuela(
            $escuela['escuela']->id,
            static fn (): Plan => Plan::factory()->create([
                'tenant_id' => $escuela['escuela']->id,
                'clases_incluidas' => null,
            ])
        );

        $this->inscribir($alumno, $plan, null);

        $consumidor = app(ConsumidorDeClases::class);
        $clase = $this->crearClase($escuela['escuela']->id);

        $consumidor->registrar($alumno, $clase->id, EstadoAsistencia::Presente);

        $this->assertNull(
            $consumidor->cupoRestante($alumno),
            'Sin cupo definido el saldo debe ser NULL, no 0.'
        );
        $this->assertSame(0, $consumidor->consumidas($alumno));
    }

    /** La regla congelada en la fila no se reescribe si después cambia la configuración. */
    public function test_cambiar_la_regla_no_reescribe_las_asistencias_viejas(): void
    {
        $escuela = $this->crearEscuela('planes-historico.cl');
        $alumno = $this->crearAlumno();

        $plan = $this->crearPlanConCupo(4);
        $this->inscribir($alumno, $plan, 4);

        $consumidor = app(ConsumidorDeClases::class);

        $primera = $this->crearClase($escuela['escuela']->id);
        $segunda = $this->crearClase($escuela['escuela']->id);

        config()->set('escuela-musica.ausencia_consume_plan', 'no');
        $consumidor->registrar($alumno, $primera->id, EstadoAsistencia::Ausente);

        config()->set('escuela-musica.ausencia_consume_plan', 'si');
        $consumidor->registrar($alumno, $segunda->id, EstadoAsistencia::Ausente);

        // Se consultan por identificador y no ordenadas, porque el orden por
        // `clase_id` no coincide con el orden de creación (los identificadores
        // son aleatorios).
        $anterior = Asistencia::query()->where('clase_id', $primera->id)->firstOrFail();
        $posterior = Asistencia::query()->where('clase_id', $segunda->id)->firstOrFail();

        // La primera sigue diciendo que no consumió, aunque hoy la regla diga
        // que sí. Sin eso, el histórico de qué pasó en un mes se reescribiría
        // cada vez que la escuela cambia su política.
        $this->assertFalse($anterior->consume_clase_plan);
        $this->assertTrue($posterior->consume_clase_plan);
    }

    public function test_la_regla_de_ausencia_expone_si_esta_definida(): void
    {
        $regla = new ReglaDeAusencia();

        config()->set('escuela-musica.ausencia_consume_plan', null);
        $this->assertFalse($regla->definida());
        $this->assertNull($regla->consume());

        config()->set('escuela-musica.ausencia_consume_plan', 'si');
        $this->assertTrue($regla->definida());
        $this->assertTrue($regla->consume());

        config()->set('escuela-musica.ausencia_consume_plan', 'no');
        $this->assertFalse($regla->consume());

        // Un valor mal escrito se trata como indefinido, no como "sí".
        config()->set('escuela-musica.ausencia_consume_plan', 'tal vez');
        $this->assertFalse($regla->definida());
    }

    private function crearPlanConCupo(int $clases): Plan
    {
        $escuelaId = $this->escuelaDePrueba;

        return $this->fijarEscuela(
            $escuelaId,
            static fn (): Plan => Plan::factory()->conCupo($clases)->create([
                'tenant_id' => $escuelaId,
            ])
        );
    }

    private function inscribir(Alumno $alumno, Plan $plan, ?int $clases): void
    {
        $escuelaId = $alumno->tenant_id;

        $this->fijarEscuela(
            $escuelaId,
            static fn (): Inscripcion => Inscripcion::query()->create([
                'tenant_id' => $escuelaId,
                'alumno_id' => $alumno->id,
                'plan_id' => $plan->id,
                'iniciada_en' => now()->startOfMonth()->toDateString(),
                'estado' => 'activa',
            ])
        );
    }

    private function crearClase(string $escuelaId): Clase
    {
        return $this->fijarEscuela(
            $escuelaId,
            static function () use ($escuelaId): Clase {
                $docente = \App\Models\Docente::factory()->create(['tenant_id' => $escuelaId]);
                $sala = \App\Models\Sala::factory()->create(['tenant_id' => $escuelaId]);

                return Clase::factory()->create([
                    'tenant_id' => $escuelaId,
                    'docente_id' => $docente->id,
                    'sala_id' => $sala->id,
                ]);
            }
        );
    }
}