<?php

declare(strict_types=1);

return [

    /*
    |-----------------------------------------------------------------------
    | Sesión
    |-----------------------------------------------------------------------
    |
    | La sesión es lo que permite que un Callback OIDC se convierta en "hay
    | alguien dentro" sin que la aplicación guarde contraseñas.
    |
    | El nombre de la cookie no lleva prefijo `__Host-` a diferencia de otras
    | aplicaciones de Hefesto, porque el prefijo exige que la cookie sea
    | `Secure` SIEMPRE y el desarrollo local por HTTP no lo es. En producción
    | el proxy termina TLS, así que el atributo `secure` se activa con
    | `SESSION_SECURE_COOKIE=true`. Ver docs/07-SEGURIDAD-Y-LEY-19628.md.
    |
    */

    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => (int) env('SESSION_LIFETIME', 480),
    'expire_on_close' => false,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION'),
    'table' => env('SESSION_TABLE', 'sessions'),
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'escuela_musica_sesion'),

    /*
    | La cookie es de sesión: no se guarda en disco. Cerrar el navegador la
    | termina. Para una aplicación interna de una PyME es lo razonable y evita
    | dejar acceso permanente a datos de menores en un equipo compartido.
    */
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => (bool) env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => env('SESSION_SAME_SITE', 'lax'),

];