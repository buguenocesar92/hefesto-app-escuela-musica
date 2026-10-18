<?php

declare(strict_types=1);

use App\Http\Middleware\ResolverTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Punto de arranque de la API de la Escuela de Musica y Arte.
 *
 * Nota sobre el esqueleto: este archivo reemplaza al que genera
 * `composer create-project laravel/laravel`. El resto del esqueleto (artisan,
 * public/index.php, storage, etc.) lo genera Composer.
 * Ver docs/04-INSTALACION.md, paso 2.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/api/v1/salud',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CSRF y sesión se aplican por el grupo `web` de Laravel.
        //
        // La interfaz la sirve Nuxt como BFF, de modo que la cookie de sesión
        // viaja en la misma origen y el token CSRF se reenvía como cabecera
        // `X-XSRF-TOKEN`. Ver docs/07, sección CSRF.
        $middleware->web(append: [
            // El orden importa: `ResolverTenant` va primero porque fija el
            // contexto de escuela del que dependen TODAS las politicas de Row
            // Level Security. Sin el, cualquier consulta a una tabla de negocio
            // falla: el aislamiento falla cerrado, nunca abierto.
            ResolverTenant::class,
        ]);

        $middleware->alias([
            'tenant.resuelto' => ResolverTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un rechazo de autorizacion no debe revelar si el recurso existe en
        // otra escuela. Se responde 404, no 403: un 403 confirmaria que el
        // identificador existe en algun lado, que es exactamente la
        // información que no se quiere entregar.
        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/v1/*')) {
                return response()->json([
                    'mensaje' => 'No encontrado.',
                ], 404);
            }

            return null;
        });
    })
    ->create();