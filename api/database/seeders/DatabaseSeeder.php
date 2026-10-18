<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Punto de entrada de los datos de ejemplo.
 *
 * ADVERTENCIA
 * -----------
 * `EscuelaDemoSeeder` crea datos que SOLO sirven para mirar la aplicación en
 * desarrollo. No crea precios ni plazos, porque esos los decide la escuela. Y no
 * se ejecuta en producción: ver `EscuelaDemoSeeder`, que se niega a correr si
 * `APP_ENV` no es local.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EscuelaDemoSeeder::class,
        ]);
    }
}