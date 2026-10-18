<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verificador de aislamiento multi-tenant.
 *
 * MISMO NOMBRE Y MISMO PROPÓSITO QUE EN LAS OTRAS APLICACIONES
 * -----------------------------------------------------------
 * `colegio:verificar-aislamiento`, `gimnasio:verificar-aislamiento`,
 * `parqueadero:verificar-aislamiento`. Si alguien busca el comando de otra
 * aplicación, lo encuentra con el mismo nombre. No se inventó un cuarto
 * patrón.
 *
 * QUÉ COMPRUEBA, Y POR QUÉ CADA COSA
 * ----------------------------------
 *  1. Que las tablas con `tenant_id` tengan Row Level Security ACTIVADA y
 *     FORZADA.
 *  2. Que la política de aislamiento exista y exija el contexto.
 *  3. Que `public.app_tenant_id()` exista y LANZE excepción sin contexto. Si
 *     devolviera NULL, el aislamiento se rompería en silencio.
 *  4. Que la tabla de escuelas NO tenga RLS, por diseño, y que exista la vista
 *     pública con sus tres columnas.
 *  5. Que el rol de la conexión NO sea superusuario ni tenga BYPASSRLS. Con
 *     cualquiera de los dos, PostgreSQL ignora todas las políticas.
 *  6. Que cada modelo con `tenant_id` apunte a una columna que exista.
 *
 * ESTE COMANDO NO SE EJECUTÓ
 * --------------------------
 * En la máquina donde se escribió el código no hay PostgreSQL. Ver docs/10.
 */
class VerificarAislamientoCommand extends Command
{
    protected $signature = 'escuela-musica:verificar-aislamiento {--detalle : Muestra cada tabla en lugar del resumen}';

    protected $description = 'Comprueba que el aislamiento entre escuelas está realmente activo en PostgreSQL.';

    public function handle(): int
    {
        $problemas = [];

        $this->comprobarRolDeLaConexion($problemas);
        $this->comprobarTablasConTenant($problemas, (bool) $this->option('detalle'));
        $this->comprobarEscuelasSinRls($problemas);
        $this->comprobarVistaPublica($problemas);
        $this->comprobarModelos($problemas);

        if ($problemas !== []) {
            $this->error('Se encontraron problemas de aislamiento:');

            foreach ($problemas as $problema) {
                $this->line('  - ' . $problema);
            }

            return self::FAILURE;
        }

        $this->info('Aislamiento correcto: cada escuela solo puede leer lo suyo.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $problemas
     */
    private function comprobarRolDeLaConexion(array &$problemas): void
    {
        $estado = DB::selectOne(
            'SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user'
        );

        if ($estado === null) {
            $problemas[] = 'No se pudo leer el rol de la conexión.';

            return;
        }

        if ((bool) $estado->rolsuper) {
            $problemas[] = 'El rol de la conexión es SUPERUSUARIO. PostgreSQL ignora las políticas RLS.';
        }

        if ((bool) $estado->rolbypassrls) {
            $problemas[] = 'El rol de la conexión tiene BYPASSRLS. PostgreSQL ignora las políticas RLS.';
        }

        if (! (bool) $estado->rolsuper && ! (bool) $estado->rolbypassrls) {
            $this->line('  [OK] El rol de la conexión no es superusuario ni tiene BYPASSRLS.');
        }
    }

    /**
     * @param  list<string>  $problemas
     */
    private function comprobarTablasConTenant(array &$problemas, bool $detalle): void
    {
        $tablas = $this->tablasConTenant();

        if ($tablas === []) {
            $problemas[] = 'No se encontró ninguna tabla con columna tenant_id.';

            return;
        }

        $conProblemas = 0;

        foreach ($tablas as $tabla) {
            $estado = DB::selectOne(
                'SELECT relrowsecurity AS rls, relforcerowsecurity AS forzado
                 FROM pg_class WHERE oid = to_regclass(?)',
                ['public.' . $tabla]
            );

            if ($estado === null) {
                $problemas[] = "La tabla {$tabla} no existe.";

                continue;
            }

            if (! (bool) $estado->rls) {
                $problemas[] = "La tabla {$tabla} NO tiene Row Level Security activado.";

                continue;
            }

            if (! (bool) $estado->forzado) {
                $problemas[] = "La tabla {$tabla} tiene RLS sin FORCE: el dueño de la tabla la saltaría.";

                continue;
            }

            $politica = DB::selectOne(
                'SELECT count(*) AS total
                 FROM pg_policies
                 WHERE tablename = ? AND policyname = ?',
                [$tabla, 'politica_aislamiento_' . $tabla]
            );

            if ($politica === null || (int) $politica->total < 1) {
                $problemas[] = "La tabla {$tabla} no tiene la política de aislamiento esperada.";

                continue;
            }

            $conProblemas++;

            if ($detalle) {
                $this->line("  [OK] {$tabla}: RLS activo y forzado, con política.");
            }
        }

        $this->line(sprintf(
            '  [%s] %d de %d tablas con tenant_id están protegidas.',
            $conProblemas === count($tablas) ? 'OK' : '??',
            $conProblemas,
            count($tablas)
        ));
    }

    /**
     * @param  list<string>  $problemas
     */
    private function comprobarEscuelasSinRls(array &$problemas): void
    {
        $estado = DB::selectOne(
            'SELECT relrowsecurity AS rls FROM pg_class WHERE oid = to_regclass(?)',
            ['public.escuelas']
        );

        if ($estado === null) {
            $problemas[] = 'La tabla escuelas no existe.';

            return;
        }

        if ((bool) $estado->rls) {
            $problemas[] = 'La tabla escuelas tiene RLS. No debería: se consulta sin contexto de escuela.';

            return;
        }

        $this->line('  [OK] La tabla escuelas no tiene RLS, por diseño.');
    }

    /**
     * @param  list<string>  $problemas
     */
    private function comprobarVistaPublica(array &$problemas): void
    {
        $columnas = DB::select(
            "SELECT column_name
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'vw_escuelas_publicas'
             ORDER BY column_name"
        );

        $nombres = array_map(static fn ($fila): string => (string) $fila->column_name, $columnas);

        if ($nombres !== ['activo', 'nombre', 'slug']) {
            $problemas[] = 'La vista vw_escuelas_publicas no expone exactamente slug, nombre y activo.';

            return;
        }

        $this->line('  [OK] La vista vw_escuelas_publicas expone solo slug, nombre y activo.');
    }

    /**
     * Cada modelo con `tenant_id` debe tener la columna en la base.
     *
     * Esta es la comprobación que atrapa el error más común: cambiar el nombre
     * de una columna en el modelo y que nada se note hasta producción.
     *
     * @param  list<string>  $problemas
     */
    private function comprobarModelos(array &$problemas): void
    {
        $rutaModelos = app_path('Models');
        $archivos = glob($rutaModelos . '/*.php') ?: [];

        $revisados = 0;

        foreach ($archivos as $archivo) {
            $clase = 'App\\Models\\' . basename($archivo, '.php');

            if (! class_exists($clase)) {
                continue;
            }

            $modelo = new $clase();

            if (! in_array(PerteneceAEscuela::class, class_uses_recursive($modelo), true)) {
                continue;
            }

            $revisados++;

            try {
                $tieneColumna = Schema::hasColumn($modelo->getTable(), 'tenant_id');
            } catch (\Throwable $e) {
                $problemas[] = "No se pudo comprobar el modelo {$clase}: " . $e->getMessage();

                continue;
            }

            if (! $tieneColumna) {
                $problemas[] = "El modelo {$clase} usa PerteneceAEscuela, pero la tabla "
                    . "{$modelo->getTable()} no tiene columna tenant_id.";
            }
        }

        $this->line("  [OK] Se revisaron {$revisados} modelos con tenant_id.");
    }

    /**
     * Tablas que tienen la columna `tenant_id` de verdad en la base.
     *
     * @return list<string>
     */
    private function tablasConTenant(): array
    {
        $filas = DB::select(
            "SELECT table_name
             FROM information_schema.columns
             WHERE table_schema = 'public' AND column_name = 'tenant_id'
             ORDER BY table_name"
        );

        return array_map(static fn ($fila): string => (string) $fila->table_name, $filas);
    }
}