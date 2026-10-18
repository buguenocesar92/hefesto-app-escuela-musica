<?php

declare(strict_types=1);

namespace Tests\Feature\Recuperacion;

use App\Enums\EstadoRecuperacion;
use App\Models\Alumno;
use App\Models\Clase;
use App\Models\Recuperacion;
use App\Services\Recuperacion\CaducidadDeRecuperacion;
use App\Services\Recuperacion\SaldoDeRecuperaciones;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Caducidad de las clases recuperadas.
 *
 * ESTA SUITE PROTEGE UNA DEUDA QUE SE PIERDE EN SILENCIO
 * -----------------------------------------------------
 * Sin caducidad configurada, una clase recuperada no vence nunca. Si el sistema
 * no muestra esa deuda, la escuela acumula obligaciones con familias que ya no
 * recuerda y nadie se entera.
 *
 * Por eso hay dos cosas que comprobar: que la fecha se calcule cuando la regla
 * está puesta, y que la deuda SIN fecha quede visible cuando no.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. Ver docs/10.
 */
class CaducidadDeClaseRecuperadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_con_plazo_configurado_la_recuperacion_calcula_su_vencimiento(): void
    {
        config()->set('escuela-musica.dias_vigencia_recuperacion', 30);

        $caducidad = new CaducidadDeRecuperacion();

        $this->assertTrue($caducidad->hayCaducidadConfigurada());

        $desde = Carbon::parse('2026-03-10 18:30:00');
        $vence = $caducidad->calculaVence($desde);

        $this->assertNotNull($vence);
        $this->assertSame('2026-04-09', $vence->toDateString());
    }

    public function test_sin_plazo_configurado_no_hay_vencimiento(): void
    {
        config()->set('escuela-musica.dias_vigencia_recuperacion', null);

        $caducidad = new CaducidadDeRecuperacion();

        $this->assertFalse($caducidad->hayCaducidadConfigurada());
        $this->assertNull(
            $caducidad->calculaVence(Carbon::now()),
            'Sin plazo configurado NO se inventa una fecha de vencimiento.'
        );
    }

    /** Un plazo de cero o negativo es un valor mal puesto, no "caduca ya". */
    public function test_un_plazo_invalido_se_trata_como_no_configurado(): void
    {
        config()->set('escuela-musica.dias_vigencia_recuperacion', 0);

        $this->assertFalse((new CaducidadDeRecuperacion())->hayCaducidadConfigurada());

        config()->set('escuela-musica.dias_vigencia_recuperacion', -5);

        $this->assertFalse((new CaducidadDeRecuperacion())->hayCaducidadConfigurada());
    }

    public function test_la_recuperacion_creada_sin_plazo_queda_sin_vencimiento_y_visible(): void
    {
        config()->set('escuela-musica.dias_vigencia_recuperacion', null);

        $escuela = $this->crearEscuela('recuperacion-sin-plazo.cl');
        $alumno = $this->crearAlumno();

        $saldo = app(SaldoDeRecuperaciones::class);

        $recuperacion = $saldo->registrar($alumno, $this->crearClase()->id);

        $this->assertNull($recuperacion->vence_el);
        $this->assertTrue($recuperacion->esSinCaducidad());
        $this->assertTrue($recuperacion->esDeuda());

        // Y aparece en la lista de deuda sin fecha: no queda escondida.
        $sinCaducidad = $saldo->sinCaducidad();
        $this->assertCount(1, $sinCaducidad);

        $resumen = $saldo->saldoDe($alumno);
        $this->assertSame(1, $resumen['pendientes']);
        $this->assertSame(1, $resumen['sin_caducidad']);
        $this->assertSame(0, $resumen['con_vencimiento']);
    }

    public function test_la_recuperacion_creada_con_plazo_antecede_a_su_vencimiento(): void
    {
        config()->set('escuela-musica.dias_vigencia_recuperacion', 30);

        $this->crearEscuela('recuperacion-con-plazo.cl');
        $alumno = $this->crearAlumno();

        $saldo = app(SaldoDeRecuperaciones::class);

        $creada = Carbon::now()->subDays(40);
        $recuperacion = $saldo->registrar($alumno, $this->crearClase()->id, null, $creada);

        $this->assertNotNull($recuperacion->vence_el);
        $this->assertFalse($recuperacion->esSinCaducidad());

        // Ya pasó la fecha y sigue pendiente: es una vencida.
        $this->assertTrue($recuperacion->pasoSuVencimiento());

        $vencidas = $saldo->vencidas();
        $this->assertCount(1, $vencidas);
    }

    public function test_una_recuperacion_pendiente_sin_vencer_no_aparece_como_vencida(): void
    {
        config()->set('escuela-musica.dias_vigencia_recuperacion', 30);

        $this->crearEscuela('recuperacion-vigente.cl');
        $alumno = $this->crearAlumno();

        $saldo = app(SaldoDeRecuperaciones::class);
        $saldo->registrar($alumno, $this->crearClase()->id, null, Carbon::now());

        $this->assertCount(0, $saldo->vencidas());
    }

    /** Al marcar como usada, la deuda desaparece del saldo. */
    public function test_una_recuperacion_usada_deja_de_ser_deuda(): void
    {
        config()->set('escuela-musica.dias_vigencia_recuperacion', 30);

        $this->crearEscuela('recuperacion-usada.cl');
        $alumno = $this->crearAlumno();

        $saldo = app(SaldoDeRecuperaciones::class);
        $recuperacion = $saldo->registrar($alumno, $this->crearClase()->id);

        $this->assertSame(1, $saldo->saldoDe($alumno)['pendientes']);

        $registro = app(\App\Services\Asistencia\RegistroDeClase::class);
        $registro->usaRecuperacion($recuperacion, $this->crearClase());

        $this->assertSame(EstadoRecuperacion::Usada, $recuperacion->fresh()?->estado);
        $this->assertSame(0, $saldo->saldoDe($alumno)['pendientes']);
    }

    /** Anular una recuperación no la borra: queda el registro de la corrección. */
    public function test_una_recuperacion_anulada_no_es_deuda_pero_sigue_registrada(): void
    {
        $this->crearEscuela('recuperacion-anulada.cl');
        $alumno = $this->crearAlumno();

        $saldo = app(SaldoDeRecuperaciones::class);
        $recuperacion = $saldo->registrar($alumno, $this->crearClase()->id);

        $registro = app(\App\Services\Asistencia\RegistroDeClase::class);
        $registro->anulaRecuperacion($recuperacion, 'Error en el registro de asistencia.');

        $this->assertSame(0, $saldo->saldoDe($alumno)['pendientes']);
        $this->assertNotNull(
            Recuperacion::query()->where('id', $recuperacion->id)->first(),
            'Anular no borra: queda constancia de que se corrigió.'
        );
    }

    private function crearClase(): Clase
    {
        $escuelaId = $this->escuelaDePrueba;

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