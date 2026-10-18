<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vista `vw_escuelas_publicas`: lo único que el selector de ingreso puede leer
 * de la tabla de escuelas.
 *
 * POR QUÉ EXISTE
 * ---------------
 * La tabla `escuelas` no lleva RLS porque se consulta antes de que exista
 * contexto de escuela. Sin una restricción adicional, el ingreso podría leer
 * la tabla entera: razón social, RUT, nombre. Esta vista es la compensación.
 *
 * QUÉ EXPONE, Y QUÉ NO
 * --------------------
 * Tres columnas, y solo tres: `slug`, `nombre` y `activo`.
 *
 * No expone `razon_social` ni `rut`. No hacen falta para elegir dónde entrar y
 * son los datos que nadie debería poder enumerar desde un formulario abierto.
 *
 * Y ESTA VISTA SE USA DE VERDAD
 * -----------------------------
 * La consulta vive en `App\Http\Controllers\AuthController::elegirEscuela()`,
 * y la pantalla de entrada (`web/app/pages/entrar.vue`) llama a ese endpoint
 * para llenar el selector. No es una vista decorativa: si se borrara, el
 * ingreso dejaría de funcionar y la prueba
 * `AislamientoDeDatosTest::test_el_selector_de_ingreso_usa_la_vista_publica`
 * fallaría.
 *
 * Lo que se hace con esto en las otras aplicaciones de Hefesto, en cambio, fue
 * crearla y nunca consultarla. Eso promete una protección que no existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_escuelas_publicas AS
            SELECT
                slug,
                nombre,
                activo
            FROM public.escuelas
            WHERE activo = true;

            COMMENT ON VIEW public.vw_escuelas_publicas IS
            'Listado de escuelas activas para el selector de ingreso. Expone solo slug, nombre y activo. No es una tabla: no se le puede escribir.';
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS public.vw_escuelas_publicas');
    }
};