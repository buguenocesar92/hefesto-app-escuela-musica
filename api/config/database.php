<?php

declare(strict_types=1);

return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    /*
    |-----------------------------------------------------------------------
    | Conexión principal
    |-----------------------------------------------------------------------
    |
    | PostgreSQL, y no MySQL ni SQLite, porque el aislamiento entre escuelas
    | depende de Row Level Security, que es específico de PostgreSQL.
    |
    | REQUISITO QUE NO SE PUEDE OMITIR
    | ---------------------------------
    | El rol que usa ESTA conexión no puede ser superusuario ni tener
    | BYPASSRLS. Con cualquiera de las dos, PostgreSQL ignora las políticas RLS
    | por completo y una escuela vería los datos de todas las demás.
    |
    | Lo comprueba `php artisan escuela-musica:verificar-aislamiento`, que hay
    | que correr en el despliegue. Ver docs/03 y docs/05.
    |
    */

    'connections' => [

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'escuela_musica'),
            'username' => env('DB_USERNAME', 'escuela_musica_app'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |-----------------------------------------------------------------------
    | Redis
    |-----------------------------------------------------------------------
    |
    | Redis guarda caché y cola de trabajos diferidos. NO es fuente de verdad:
    | si se pierde, la aplicación sigue funcionando, solo pierde la caché y
    | los trabajos encolados quedan sin procesar hasta la próxima ejecución.
    |
    */
    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'escuela_musica_db_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];