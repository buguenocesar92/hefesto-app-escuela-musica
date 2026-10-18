<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * AISLAMIENTO ENTRE ESCUELAS: Row Level Security (RLS) de PostgreSQL.
 *
 * Esta migración es el corazón del producto. Reutiliza el patrón ya decidido y
 * documentado en `apps/colegio/docs/03-MULTITENANCY-Y-ROLES.md`:
 * columna `tenant_id` más RLS, sin inventar un cuarto enfoque.
 *
 * MECANISMO
 * ---------
 * 1. `public.app_tenant_id()` lee el valor de la variable de sesión
 *    `app.tenant_id`. Si no está definida, la función LANZA un error. No
 *    devuelve NULL ni una cadena vacía: falla de forma ruidosa.
 * 2. Cada tabla de negocio tiene una política que exige
 *    `tenant_id = app_tenant_id()`, tanto al leer (USING) como al escribir
 *    (WITH CHECK).
 * 3. `FORCE ROW LEVEL SECURITY` hace que la política se aplique también al
 *    dueño de la tabla. Sin FORCE, el dueño la saltaría.
 * 4. La variable se fija con `set_config('app.tenant_id', ..., true)`, es decir
 *    con alcance de TRANSACCIÓN. Al confirmar o revertir la transacción,
 *    PostgreSQL la descarta sola. Por eso no puede quedar colgada en una
 *    conexión reutilizada por el worker de colas, que es donde un `SET` normal
 *    filtraría datos de una escuela a otra.
 *
 * LAS TABLAS SIN RLS, Y POR QUÉ
 * -----------------------------
 *  - `escuelas`: se consulta durante el acceso, cuando todavía no hay sesión y
 *    por lo tanto no hay contexto. Compensado con la vista
 *    `vw_escuelas_publicas` (migración 2026_10_18_000021), que es la que
 *    consulta el selector de ingreso, y con que el rol de la aplicación no sea
 *    superusuario.
 *  - `sessions`: la resuelve Laravel por identificador de sesión, no la
 *    aplicación.
 *  - `migrations`: es infraestructura, no datos de nadie.
 *
 * REQUISITO DE DESPLIEGUE: el rol que usa la aplicación NO puede ser
 * superusuario ni tener BYPASSRLS. Con superusuario, PostgreSQL ignora las
 * políticas por completo. El comando
 * `php artisan escuela-musica:verificar-aislamiento` lo comprueba.
 *
 * ADVERTENCIA HONESTA: esta migración NO se ejecutó. No había PostgreSQL
 * disponible en el entorno donde se escribió el código. Ver docs/10.
 */
return new class extends Migration
{
    /**
     * Tablas con datos de negocio, y por lo tanto con RLS.
     *
     * @var list<string>
     */
    private array $tablasProtegidas = [
        'docentes',
        'usuarios',
        'salas',
        'alumnos',
        'responsables',
        'alumno_responsables',
        'planes',
        'inscripciones',
        'pagos',
        'clases',
        'inscripciones_clase',
        'asistencias',
        'recuperaciones',
        'movimientos_clase_plan',
        'instrumentos',
        'prestamos_instrumento',
        'bitacora_accesos',
    ];

    public function up(): void
    {
        // 1. Función de contexto. SECURITY INVOKER (lo normal): no necesita
        //    privilegios propios.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
            LANGUAGE plpgsql
            STABLE
            AS $$
            DECLARE
                valor text;
            BEGIN
                valor := current_setting('app.tenant_id', true);

                IF valor IS NULL OR valor = '' THEN
                    RAISE EXCEPTION USING
                        ERRCODE = 'insufficient_privilege',
                        MESSAGE = 'No hay contexto de escuela en la conexión (app.tenant_id no está definida). Se aborta la operación para no exponer datos de otra escuela.',
                        HINT = 'La petición no pasó por App\Http\Middleware\ResolverTenant, o la operación se ejecutó fuera de una transacción.';
                END IF;

                RETURN valor::uuid;
            END
            $$;
        SQL);

        DB::unprepared(<<<'SQL'
            COMMENT ON FUNCTION public.app_tenant_id() IS
            'Devuelve la escuela de la petición. Lanza excepción si no hay contexto: el aislamiento falla cerrado.';
        SQL);

        // 2. Políticas y activación por tabla.
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne(
                'SELECT to_regclass(?) AS tabla',
                ['public.' . $tabla]
            );

            // to_regclass devuelve NULL si la tabla todavía no existe.
            if ($existe === null || $existe->tabla === null) {
                continue;
            }

            DB::unprepared('ALTER TABLE public.' . $tabla . ' ENABLE ROW LEVEL SECURITY');

            // FORCE hace que la política aplique también al dueño. Es
            // indispensable: en desarrollo el dueño suele ser el mismo rol que
            // corre la aplicación, y sin FORCE el aislamiento sería decorativo
            // justo en el entorno donde se prueba.
            DB::unprepared('ALTER TABLE public.' . $tabla . ' FORCE ROW LEVEL SECURITY');

            $nombre = 'politica_aislamiento_' . $tabla;

            DB::unprepared(<<<SQL
                DROP POLICY IF EXISTS {$nombre} ON public.{$tabla};

                CREATE POLICY {$nombre} ON public.{$tabla}
                    FOR ALL
                    TO PUBLIC
                    USING (tenant_id = public.app_tenant_id())
                    WITH CHECK (tenant_id = public.app_tenant_id());
            SQL);
        }

        $this->verificarColumnaTenant();
    }

    public function down(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne('SELECT to_regclass(?) AS tabla', ['public.' . $tabla]);

            if ($existe === null || $existe->tabla === null) {
                continue;
            }

            DB::unprepared('DROP POLICY IF EXISTS politica_aislamiento_' . $tabla . ' ON public.' . $tabla);
            DB::unprepared('ALTER TABLE public.' . $tabla . ' NO FORCE ROW LEVEL SECURITY');
            DB::unprepared('ALTER TABLE public.' . $tabla . ' DISABLE ROW LEVEL SECURITY');
        }

        DB::unprepared('DROP FUNCTION IF EXISTS public.app_tenant_id()');
    }

    /**
     * Falla ruidosamente si alguna tabla de la lista no tiene `tenant_id`.
     *
     * Sin esto, un error de tipeo dejaría una tabla de datos de negocio sin
     * política y la migración pasaría igual, en silencio. Con datos de menores y
     * de pagos, un silencio así es inaceptable.
     */
    private function verificarColumnaTenant(): void
    {
        foreach ($this->tablasProtegidas as $tabla) {
            $existe = DB::selectOne(
                'SELECT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_schema = ''public'' AND table_name = ? AND column_name = ''tenant_id''
                 ) AS tiene_columna',
                [$tabla]
            );

            if ($existe === null || ! $existe->tiene_columna) {
                throw new RuntimeException(
                    'La tabla public.' . $tabla . ' no tiene columna tenant_id y no puede protegerse con RLS. '
                    . 'Revise la migración antes de continuar.'
                );
            }
        }
    }
};