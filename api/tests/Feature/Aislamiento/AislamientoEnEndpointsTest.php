<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Alumno;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El aislamiento, visto desde HTTP.
 *
 * POR QUÉ HAY UNA SUITE APARTE DE LA DE LA BASE
 * ---------------------------------------------
 * `AislamientoDeDatosTest` comprueba que PostgreSQL filtre bien. Esta comprueba
 * lo otro: que un identificador de otra escuela devuelva 404 y no 403.
 *
 * La diferencia no es cosmética. Un 403 dice "esto existe pero no puedes".
 * Un 404 dice "esto no existe". Con 403, un atacante puede recorrer
 * identificadores y aprender qué escuelas tienen cuántos alumnos, sin ver ni un
 * solo dato personal.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. Ver docs/10.
 */
class AislamientoEnEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ficha_de_un_alumno_de_otra_escuela_responde_404_y_no_403(): void
    {
        $primera = $this->crearEscuela('endpoint-a.cl');
        $alumnoPropio = $this->crearAlumno();

        $segunda = $this->crearEscuela('endpoint-b.cl');
        $alumnoAjeno = $this->crearAlumno();

        // Se entra como la dirección de la PRIMERA escuela.
        $this->comoUsuario($primera['usuario']);

        $respuesta = $this->getJson('/api/v1/alumnos/' . $alumnoPropio->id);
        $respuesta->assertOk();

        $respuestaAjena = $this->getJson('/api/v1/alumnos/' . $alumnoAjeno->id);

        $respuestaAjena->assertNotFound();
        $this->assertNotSame(
            403,
            $respuestaAjena->getStatusCode(),
            'Un rechazo de autorización debe responder 404, no 403: '
            . 'un 403 confirmaría que el identificador existe en otra escuela.'
        );
    }

    public function test_un_docente_no_ve_los_datos_personales_de_un_alumno(): void
    {
        $escuela = $this->crearEscuela('endpoint-c.cl');
        $alumno = $this->crearAlumno();

        $docente = $this->fijarEscuela(
            $escuela['escuela']->id,
            static fn () => \App\Models\Usuario::factory()->create([
                'tenant_id' => $escuela['escuela']->id,
                'rol' => 'docente',
            ])
        );

        $this->comoUsuario($docente);

        $respuesta = $this->getJson('/api/v1/alumnos/' . $alumno->id);

        $respuesta->assertOk();

        // El docente puede ver que el alumno existe y está en la clase, pero no
        // su dirección ni su fecha de nacimiento.
        $this->assertNull(
            $respuesta->json('alumno.datos_personales'),
            'Un docente no tiene por qué ver la dirección de la casa de un menor.'
        );
    }

    public function test_un_docente_no_ve_los_pagos(): void
    {
        $escuela = $this->crearEscuela('endpoint-d.cl');
        $alumno = $this->crearAlumno();

        $docente = $this->fijarEscuela(
            $escuela['escuela']->id,
            static fn () => \App\Models\Usuario::factory()->create([
                'tenant_id' => $escuela['escuela']->id,
                'rol' => 'docente',
            ])
        );

        $this->fijarEscuela($escuela['escuela']->id);
        Pago::factory()->conMonto(1)->create([
            'tenant_id' => $escuela['escuela']->id,
            'alumno_id' => $alumno->id,
        ]);

        $this->comoUsuario($docente);

        $this->getJson('/api/v1/alumnos/' . $alumno->id . '/pagos')->assertNotFound();
        $this->getJson('/api/v1/pagos')->assertNotFound();
    }

    public function test_sin_sesion_las_rutas_de_negocio_no_abren(): void
    {
        $escuela = $this->crearEscuela('endpoint-e.cl');

        $this->getJson('/api/v1/tablero')->assertUnauthorized();
        $this->getJson('/api/v1/alumnos')->assertUnauthorized();
        $this->getJson('/api/v1/pagos')->assertUnauthorized();

        // El listado de escuelas sí es público: es el selector del ingreso.
        $this->getJson('/api/v1/auth/escuelas')->assertOk();
    }

    public function test_el_tablero_no_trae_pagos_a_quien_no_corresponde(): void
    {
        $escuela = $this->crearEscuela('endpoint-f.cl');

        $docente = $this->fijarEscuela(
            $escuela['escuela']->id,
            static fn () => \App\Models\Usuario::factory()->create([
                'tenant_id' => $escuela['escuela']->id,
                'rol' => 'docente',
            ])
        );

        $this->comoUsuario($docente);

        $respuesta = $this->getJson('/api/v1/tablero');

        $respuesta->assertOk();

        // El bloque no debe viajar siquiera, ni en cero: no viaja.
        $this->assertArrayNotHasKey('pagos_pendientes', $respuesta->json());

        // Y sí debe viajar lo que el docente sí necesita ver.
        $this->assertArrayHasKey('clases_de_hoy', $respuesta->json());
    }
}