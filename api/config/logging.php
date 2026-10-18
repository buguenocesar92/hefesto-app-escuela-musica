<?php

declare(strict_types=1);

return [

    /*
    |-----------------------------------------------------------------------
    | Registro de eventos
    |-----------------------------------------------------------------------
    |
    | El canal `stack` escribe en el archivo del día. El archivo rota a diario
    | porque la fuente de verdad no es el log: es la bitácora `bitacora_accesos`,
    | que vive en la base de datos y es lo que el dueño consulta.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'daily')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => (int) env('LOG_DAYS', 14),
            'replace_placeholders' => true,
        ],

        /*
        | Canal limpio: solo advertencias y errores, sin datos de la petición.
        | Es el que debe mirar quien opera la escuela, porque el archivo diario
        | puede contener identificadores de alumno en las URL.
        */
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => Monolog\Handler\StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];