<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\ContextoEscuela;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija la escuela de la petición para que la RLS sepa a quién pertenece cada
 * fila.
 *
 * MISMO PATRÓN QUE EL COLEGIO Y EL PORTAL DE AUTOSERVICIO
 * -------------------------------------------------------
 * Se aplica a todo el grupo `web` (ver bootstrap/app.php), ANTES que el
 * middleware `auth` de la ruta. Por eso no puede usar `$request->user()`:
 * resuelve el usuario por su cuenta con el guard `web`, que ya lee la
 * sesión.
 *
 * Peticiones sin sesión (ingreso, callback, salud) pasan sin transacción. Si
 * esas rutas tocaran una tabla protegida, `public.app_tenant_id()` lanzaría
 * excepción y la petición fallaría: el aislamiento falla cerrado, nunca
 * abierto.
 */
class ResolverTenant
{
    public function __construct(private readonly ContextoEscuela $contexto) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = auth()->guard('web')->user();

        if ($usuario === null) {
            return $next($request);
        }

        if ($usuario->tenant_id === null) {
            // Una cuenta sin escuela no puede operar. Se corta aquí, antes de
            // tocar la base de datos.
            abort(403, 'La cuenta no está asociada a ninguna escuela.');
        }

        return $this->contexto->ejecutarAislado(
            $usuario->tenant_id,
            static fn () => $next($request)
        );
    }
}