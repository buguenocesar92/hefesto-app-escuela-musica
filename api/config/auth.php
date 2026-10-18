<?php

declare(strict_types=1);

return [

    /*
    |-----------------------------------------------------------------------
    | Autenticación
    |-----------------------------------------------------------------------
    |
    | NO hay contraseñas en esta aplicación. El guard `web` usa la SESIÓN, y la
    | sesión la abre el callback OIDC de Authentik. No hay driver de contraseñas
    | que configurar, y eso es deliberado: la escuela no guarda contraseñas de
    | empleados ni de familias.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => null,
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'usuarios',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => App\Models\Usuario::class,
        ],
    ],

    /*
    | Se deja vacío a propósito: si algún día apareciera un campo de contraseña
    | en `usuarios`, este bloque seguiría vacío y el driver quedaría
    | inaccesible.
    */
    'passwords' => [],

    'password_timeout' => 10800,

];