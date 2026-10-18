<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Alumno;
use App\Models\Clase;
use App\Models\Docente;
use App\Models\Escuela;
use App\Models\Instrumento;
use App\Models\Sala;
use App\Services\Tenancy\ContextoEscuela;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos de ejemplo para desarrollo.
 *
 * LO QUE ESTE SEEDER NO HACE, Y ES LO IMPORTANTE
 * -----------------------------------------------
 * No crea:
 *
 *  - precios de plan. Un precio inventado termina en una factura.
 *  - plazos de vigencia de recuperación. Poner 90 días sería decidir por la
 *    escuela cuando caduca una deuda.
 *  - la regla de consumo por ausencia. Es una decisión de negocio.
 *  - alumnos menores sin responsable y sin autorización.
 *
 * SE NIEGA A CORRER FUERA DE DESARROLLO
 * -------------------------------------
 * Una escuela de ejemplo que alguien olvidó borrar aparece en el selector de
 * ingreso de producción. Por eso el seeder aborta si `APP_ENV` no es `local`.
 */
class EscuelaDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'EscuelaDemoSeeder no se ejecuta en producción. '
                . 'Cree la escuela real con: php artisan escuela-musica:crear'
            );
        }

        $escuela = Escuela::query()->create([
            'slug' => 'escuela-demo',
            'nombre' => 'Escuela de Música y Arte (demostración)',
            'razon_social' => null,
            'rut' => null,
            'activo' => true,
        ]);

        $contexto = app(ContextoEscuela::class);

        $contexto->ejecutarAislado($escuela->id, function () use ($escuela): void {
            $docentes = Docente::factory()->count(2)->create([
                'tenant_id' => $escuela->id,
            ]);

            $salas = Sala::factory()->count(2)->create([
                'tenant_id' => $escuela->id,
            ]);

            $alumnos = Alumno::factory()->count(6)->create([
                'tenant_id' => $escuela->id,
            ]);

            foreach ($docentes->take(2) as $indice => $docente) {
                Clase::factory()->create([
                    'tenant_id' => $escuela->id,
                    'docente_id' => $docente->id,
                    'sala_id' => $salas[$indice]->id,
                    'nombre' => 'Clase de muestra ' . ($indice + 1),
                ]);

                foreach ($alumnos->take(3) as $alumno) {
                    $clase = Clase::query()
                        ->where('docente_id', $docente->id)
                        ->first();

                    $clase?->alumnos()->attach($alumno->id);
                }
            }

            Instrumento::factory()->count(3)->create([
                'tenant_id' => $escuela->id,
            ]);
        });

        $this->command?->info('Escuela de demostración creada: escuela-demo');
        $this->command?->warn('Los planes se crean SIN precio y SIN cupo: es la escuela quien los define.');
        $this->command?->warn('Las reglas de negocio quedan sin configurar a propósito.');
    }
}