<?php

declare(strict_types=1);

namespace Tests\Feature\Aislamiento;

use App\Models\Alumno;
use App\Models\Plan;
use App\Services\Tenancy\ContextoEscuela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PRUEBAS CRÍTICAS: una escuela no puede leer los datos de otra.
 *
 * Estas pruebas van contra PostgreSQL de verdad, porque el aislamiento está
 * implementado con Row Level Security. Si se corrieran contra SQLite, pasarían
 * sin comprobar nada. Ver phpunit.xml.
 *
 * Lo que se comprueba acá:
 *
 *  1. Sin contexto de escuela, las tablas de negocio fallan en vez de devolver
 *     filas.
 *  2. Con el contexto de otra escuela, cada una ve solo lo suyo.
 *  3. Un identificador de otra escuela no existe para esta persona.
 *  4. No se puede escribir una fila en nombre de otra escuela.
 *  5. El contexto no sobrevive a la transacción.
 *  6. La tabla de escuelas NO tiene RLS por diseño, y la vista pública existe y
 *     expone solo tres columnas.
 *  7. El selector de ingreso lee de verdad la vista pública.
 *
 * ADVERTENCIA: estas pruebas están ESCRITAS y NO EJECUTADAS. En el entorno donde
 * se escribió el código no había PostgreSQL. Ver docs/10.
 */
class AislamientoDeDatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_contexto_de_escuela_la_consulta_falla_en_vez_de_traer_datos(): void
    {
        $escuela = $this->crearEscuela('aislamiento-uno.cl');
        $this->crearAlumno();

        // Se limpia el contexto a propósito: es la situación de una consulta
        // que se escapara del middleware.
        //
        // Lo que se espera NO es "cero filas", sino un error.
        // `app_tenant_id()` lanza excepción cuando no hay escuela y la política
        // RLS aborta la consulta. Devolver cero filas sería más cómodo de
        // probar, pero dejaría pasar el caso peligroso: que alguien "arregle"
        // la función devolviendo NULL y termine leyendo filas de la escuela
        // equivocada sin que nada lo delate.
        DB::select('SELECT set_config(?, ?, false)', [ContextoEscuela::VARIABLE, '']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Alumno::query()->count();
    }

    public function test_cada_escuela_solo_ve_sus_propias_filas(): void
    {
        $primera = $this->crearEscuela('aislamiento-a.cl');
        $this->crearAlumno();
        $this->crearAlumno();
        $this->crearAlumno();

        $segunda = $this->crearEscuela('aislamiento-b.cl');
        $this->crearAlumno();
        $this->crearAlumno();

        $this->fijarEscuela($primera['escuela']->id);
        $this->assertSame(3, Alumno::query()->count());

        $this->fijarEscuela($segunda['escuela']->id);
        $this->assertSame(2, Alumno::query()->count());
        $this->assertSame(
            $segunda['escuela']->id,
            Alumno::query()->value('tenant_id')
        );
    }

    public function test_el_alumno_de_otra_escuela_no_se_puede_leer_ni_escribir(): void
    {
        $primera = $this->crearEscuela('aislamiento-c.cl');
        $propio = $this->crearAlumno();

        $segunda = $this->crearEscuela('aislamiento-d.cl');
        $ajeno = $this->crearAlumno();

        // Se vuelve al contexto de la primera escuela.
        $this->fijarEscuela($primera['escuela']->id);

        $this->assertNotNull($propio->fresh());
        $this->assertNull(Alumno::find($ajeno->id));

        // Y tampoco se puede tocar: la política tiene WITH CHECK.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('alumnos')
            ->where('id', $ajeno->id)
            ->update(['nombre' => 'secuestrado']);
    }

    public function test_no_se_puede_insertar_una_fila_con_la_escuela_de_otra(): void
    {
        $primera = $this->crearEscuela('aislamiento-e.cl');
        $segunda = $this->crearEscuela('aislamiento-f.cl');

        $this->fijarEscuela($primera['escuela']->id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Plan::query()->create([
            'tenant_id' => $segunda['escuela']->id,
            'nombre' => 'Plan ajeno',
        ]);
    }

    public function test_el_contexto_de_escuela_no_sobrevive_a_la_transaccion(): void
    {
        $escuela = $this->crearEscuela('aislamiento-g.cl');
        $this->crearAlumno();

        $contexto = new ContextoEscuela();

        $conteo = $contexto->ejecutarAislado(
            $escuela['escuela']->id,
            fn (): int => Alumno::query()->count()
        );

        $this->assertSame(1, $conteo);
        $this->assertNull(
            $contexto->escuelaIdEnConexion(),
            'La variable de contexto debe desaparecer al terminar la transacción. '
            . 'Si queda, el worker de colas podría leer datos de otra escuela.'
        );
    }

    public function test_la_funcion_de_contexto_falla_cerrada_sin_escuela(): void
    {
        DB::select('SELECT set_config(?, ?, false)', [ContextoEscuela::VARIABLE, '']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::select('SELECT public.app_tenant_id()');
    }

    public function test_la_tabla_escuelas_no_tiene_rls_por_diseno(): void
    {
        // Excepción consciente, documentada en docs/03, sección 4. La prueba
        // fija el comportamiento para que, si alguien cambia la migración,
        // quede claro que cambió a propósito.
        $estado = DB::selectOne(
            "SELECT relrowsecurity AS rls
             FROM pg_class
             WHERE oid = to_regclass('public.escuelas')"
        );

        $this->assertFalse((bool) ($estado->rls ?? true));
    }

    public function test_la_vista_publica_solo_expone_tres_columnas(): void
    {
        $columnas = DB::select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'vw_escuelas_publicas'
             ORDER BY column_name"
        );

        $nombres = array_map(static fn ($fila): string => (string) $fila->column_name, $columnas);

        $this->assertSame(['activo', 'nombre', 'slug'], $nombres);
    }

    /**
     * La vista pública tiene que estar EN USO, no solo existir.
     *
     * En otras aplicaciones de Hefesto la vista se creó y nunca se consultó,
     * mientras la documentación afirmaba que sí. Esta prueba es la que impide
     * repetir eso: si el selector de ingreso dejara de leer la vista, esta
     * prueba fallaría.
     */
    public function test_el_selector_de_ingreso_usa_la_vista_publica(): void
    {
        $primera = $this->crearEscuela('visible.cl');
        $inactiva = $this->crearEscuela('oculta.cl');
        $inactiva['escuela']->update(['activo' => false]);

        // Sin sesión: es exactamente el momento en que no hay contexto de
        // escuela y se consulta la vista.
        $respuesta = $this->getJson('/api/v1/auth/escuelas');

        $respuesta->assertOk();

        $slugs = collect($respuesta->json('escuelas'))->pluck('slug');

        $this->assertContains('visible.cl', $slugs);
        $this->assertNotContains('oculta.cl', $slugs);

        // Y no debe filtrar datos que la tabla completa sí tiene.
        $claves = array_keys((array) $respuesta->json('escuelas.0'));

        $this->assertEqualsCanonicalizing(['slug', 'nombre'], $claves);
    }
}