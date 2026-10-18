<?php

declare(strict_types=1);

namespace Tests\Feature\Agenda;

use App\Models\Clase;
use App\Models\Docente;
use App\Models\Sala;
use App\Services\Agenda\DetectorDeSolapamiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Choque de horario en la agenda.
 *
 * POR QUÉ EXISTE ESTA SUITE
 * -------------------------
 * El problema operativo del día no es el Matcher de nombres: es que la misma
 * persona, o la misma sala, esté en dos lugares a la vez. Esto no lo impone la
 * base de datos (ver la nota de `DetectorDeSolapamiento`), así que si no se
 * prueba, nadie sabe si funciona.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. Ver docs/10.
 */
class SolapamientoDeAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_mismo_docente_no_puede_estar_en_dos_clases_a_la_vez(): void
    {
        $escuela = $this->crearEscuela('agenda-docente.cl');
        $this->escuelaDePrueba = $escuela['escuela']->id;

        $docente = Docente::factory()->create(['tenant_id' => $this->escuelaDePrueba]);
        $salas = Sala::factory()->count(2)->create(['tenant_id' => $this->escuelaDePrueba]);

        $this->crearClase($docente->id, $salas[0]->id, '2026-03-10 18:00', '2026-03-10 19:00');

        $detector = new DetectorDeSolapamiento();

        // Mismo docente, distinta sala, misma hora: igual choca, porque el
        // cuerpo de una persona no se puede partir.
        $hayChoque = $detector->hayChoque(
            $docente->id,
            $salas[1]->id,
            Carbon::parse('2026-03-10 18:30'),
            Carbon::parse('2026-03-10 19:30'),
        );

        $this->assertTrue($hayChoque, 'El mismo docente no puede tener dos clases superpuestas.');
    }

    public function test_la_misma_sala_no_puede_estar_en_dos_clases_a_la_vez(): void
    {
        $escuela = $this->crearEscuela('agenda-sala.cl');
        $this->escuelaDePrueba = $escuela['escuela']->id;

        $docentes = Docente::factory()->count(2)->create(['tenant_id' => $this->escuelaDePrueba]);
        $sala = Sala::factory()->create(['tenant_id' => $this->escuelaDePrueba]);

        $this->crearClase($docentes[0]->id, $sala->id, '2026-03-10 18:00', '2026-03-10 19:00');

        $detector = new DetectorDeSolapamiento();

        $this->assertTrue(
            $detector->hayChoque(
                $docentes[1]->id,
                $sala->id,
                Carbon::parse('2026-03-10 18:30'),
                Carbon::parse('2026-03-10 19:30'),
            ),
            'La misma sala no puede tener dos clases superpuestas.'
        );
    }

    public function test_docente_y_sala_distintos_no_chocan(): void
    {
        $escuela = $this->crearEscuela('agenda-sin-choque.cl');
        $this->escuelaDePrueba = $escuela['escuela']->id;

        $docentes = Docente::factory()->count(2)->create(['tenant_id' => $this->escuelaDePrueba]);
        $salas = Sala::factory()->count(2)->create(['tenant_id' => $this->escuelaDePrueba]);

        $this->crearClase($docentes[0]->id, $salas[0]->id, '2026-03-10 18:00', '2026-03-10 19:00');

        $detector = new DetectorDeSolapamiento();

        $this->assertFalse(
            $detector->hayChoque(
                $docentes[1]->id,
                $salas[1]->id,
                Carbon::parse('2026-03-10 18:30'),
                Carbon::parse('2026-03-10 19:30'),
            ),
            'Con docente y sala distintos no hay choque.'
        );
    }

    /**
     * Dos clases seguidas NO se pisan.
     *
     * Es el caso del límite: una de 18:00 a 19:00 y otra de 19:00 a 20:00. Con
     * una comparación mal hecha (`<=`) el detector rechazaría la agenda entera
     * de cualquier escuela.
     */
    public function test_dos_clases_seguidas_no_se_pisan(): void
    {
        $escuela = $this->crearEscuela('agenda-seguidas.cl');
        $this->escuelaDePrueba = $escuela['escuela']->id;

        $docente = Docente::factory()->create(['tenant_id' => $this->escuelaDePrueba]);
        $sala = Sala::factory()->create(['tenant_id' => $this->escuelaDePrueba]);

        $this->crearClase($docente->id, $sala->id, '2026-03-10 18:00', '2026-03-10 19:00');

        $detector = new DetectorDeSolapamiento();

        $this->assertFalse(
            $detector->hayChoque(
                $docente->id,
                $sala->id,
                Carbon::parse('2026-03-10 19:00'),
                Carbon::parse('2026-03-10 20:00'),
            ),
            'Una clase que empieza justo cuando termina la anterior no choca.'
        );
    }

    /** Al editar una clase, ella misma no cuenta como choque. */
    public function test_editar_una_clase_no_la_hace_chocar_consigo_misma(): void
    {
        $escuela = $this->crearEscuela('agenda-edicion.cl');
        $this->escuelaDePrueba = $escuela['escuela']->id;

        $docente = Docente::factory()->create(['tenant_id' => $this->escuelaDePrueba]);
        $sala = Sala::factory()->create(['tenant_id' => $this->escuelaDePrueba]);

        $clase = $this->crearClase($docente->id, $sala->id, '2026-03-10 18:00', '2026-03-10 19:00');

        $detector = new DetectorDeSolapamiento();

        $this->assertTrue(
            $detector->hayChoque(
                $docente->id,
                $sala->id,
                Carbon::parse('2026-03-10 18:30'),
                Carbon::parse('2026-03-10 19:30'),
            ),
            'Sin ignorar la clase, el detector la encuentra.'
        );

        $this->assertFalse(
            $detector->hayChoque(
                $docente->id,
                $sala->id,
                Carbon::parse('2026-03-10 18:30'),
                Carbon::parse('2026-03-10 19:30'),
                $clase->id,
            ),
            'Al ignorarla, no debe chocar consigo misma.'
        );
    }

    /** Una clase anulada libera su horario. */
    public function test_una_clase_anulada_no_bloquea_el_horario(): void
    {
        $escuela = $this->crearEscuela('agenda-anulada.cl');
        $this->escuelaDePrueba = $escuela['escuela']->id;

        $docente = Docente::factory()->create(['tenant_id' => $this->escuelaDePrueba]);
        $sala = Sala::factory()->create(['tenant_id' => $this->escuelaDePrueba]);

        $this->crearClase($docente->id, $sala->id, '2026-03-10 18:00', '2026-03-10 19:00', 'anulada');

        $detector = new DetectorDeSolapamiento();

        $this->assertFalse(
            $detector->hayChoque(
                $docente->id,
                $sala->id,
                Carbon::parse('2026-03-10 18:00'),
                Carbon::parse('2026-03-10 19:00'),
            ),
            'Una clase anulada no debería bloquear el horario.'
        );
    }

    public function test_el_mensaje_distingue_el_motivo_del_choque(): void
    {
        $escuela = $this->crearEscuela('agenda-mensaje.cl');
        $this->escuelaDePrueba = $escuela['escuela']->id;

        $docente = Docente::factory()->create(['tenant_id' => $this->escuelaDePrueba]);
        $salas = Sala::factory()->count(2)->create(['tenant_id' => $this->escuelaDePrueba]);

        $clase = $this->crearClase($docente->id, $salas[0]->id, '2026-03-10 18:00', '2026-03-10 19:00');

        $detector = new DetectorDeSolapamiento();
        $mensaje = $detector->mensaje([$clase], $docente->id, $salas[1]->id);

        $this->assertStringContainsString('El docente', $mensaje);
        $this->assertStringNotContainsString('La sala', $mensaje);
    }

    private function crearClase(
        string $docenteId,
        string $salaId,
        string $inicio,
        string $termina,
        string $estado = 'agendada'
    ): Clase {
        $escuelaId = $this->escuelaDePrueba;

        return $this->fijarEscuela(
            $escuelaId,
            static fn (): Clase => Clase::factory()->create([
                'tenant_id' => $escuelaId,
                'docente_id' => $docenteId,
                'sala_id' => $salaId,
                'inicio' => Carbon::parse($inicio),
                'termina' => Carbon::parse($termina),
                'estado' => $estado,
            ])
        );
    }
}