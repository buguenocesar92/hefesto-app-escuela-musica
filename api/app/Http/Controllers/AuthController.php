<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Auth\DescubridorOidc;
use App\Services\Auth\ResolvedorDeCuenta;
use App\Services\Auth\VerificadorIdToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ingreso y salida. No hay contraseñas: todo pasa por Authentik.
 *
 * POR QUÉ TODO ESTÁ BAJO `/api/v1/auth`
 * -------------------------------------
 * Porque la interfaz habla con la API únicamente por el proxy de `web/server`,
 * que solo atiende `/api/**`. Una ruta de ingreso fuera de ese prefijo no
 * llegaría a la API desde el navegador.
 *
 * EL SELECTOR DE ESCUELA USA LA VISTA PÚBLICA
 * --------------------------------------------
 * `escuelas()` lee `vw_escuelas_publicas`, no la tabla `escuelas`. La tabla
 * completa no tiene RLS porque se consulta antes de que exista contexto de
 * escuela, y por eso existe la vista: expone solo `slug`, `nombre` y `activo`,
 * sin razón social ni RUT.
 *
 * Esta vista SE USA DE VERDAD: es la que llena el selector de la pantalla de
 * entrada (`web/app/pages/entrar.vue`). No es una vista decorativa. Si se
 * borrara, el ingreso dejaría de funcionar, y la prueba
 * `test_el_selector_de_ingreso_usa_la_vista_publica` fallaría.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly DescubridorOidc $descubridor,
        private readonly VerificadorIdToken $verificador,
        private readonly ResolvedorDeCuenta $resolvedor,
    ) {}

    /**
     * Escuelas disponibles para entrar.
     *
     * Sin sesión y sin contexto de escuela, que es exactamente el momento en el
     * que la tabla `escuelas` no tiene protección de RLS. La vista es la
     * compensación.
     */
    public function escuelas(): JsonResponse
    {
        $escuelas = DB::table('vw_escuelas_publicas')
            ->select('slug', 'nombre')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'escuelas' => $escuelas,
        ]);
    }

    /**
     * Prepara el ingreso y devuelve la dirección del proveedor.
     *
     * Devuelve JSON en vez de redirigir porque para la API esto es una
     * petición de servidor: la redirección la hace el navegador cuando
     * navega a la dirección que se le entrega. Así no depende de que una
     * cabecera `Location` atraviese el proxy, que es un detalle frágil.
     *
     * El estado es un valor aleatorio que se guarda en la sesión y se
     * comprueba en el callback. Sin él, un callback ajeno podría abrir sesión
     * en el navegador de otra persona.
     */
    public function entrar(Request $peticion): JsonResponse
    {
        $slug = (string) $peticion->input('escuela', '');

        if ($slug === '') {
            return response()->json([
                'mensaje' => 'Elija una escuela para continuar.',
            ], 422);
        }

        $redirigir = $this->destinoSeguro($peticion->input('redirigir_a'));

        $estado = Str::random(40);
        $peticion->session()->put('estado_oidc', $estado);
        $peticion->session()->put('escuela_oidc', $slug);
        $peticion->session()->put('redirigir_oidc', $redirigir);

        $url = $this->descubridor->urlDeAutorizacion()
            . '?' . http_build_query([
                'client_id' => (string) config('authentik.client_id'),
                'redirect_uri' => (string) config('authentik.redirect_uri'),
                'response_type' => 'code',
                'scope' => (string) config('authentik.scope'),
                'state' => $estado,
            ]);

        return response()->json([
            'url_autorizacion' => $url,
            'redirigir_a' => $redirigir,
        ]);
    }

    /**
     * Callback del proveedor: abre la sesión.
     *
     * Responde JSON porque lo consume el proxy, que lo convierte en una
     * navegación (ver `web/server/api/[...ruta].ts`).
     */
    public function callback(Request $peticion): JsonResponse
    {
        $estadoEsperado = $peticion->session()->pull('estado_oidc');
        $slug = (string) $peticion->session()->pull('escuela_oidc', '');
        $redirigir = $this->destinoSeguro($peticion->session()->pull('redirigir_oidc'));

        if ($estadoEsperado === null || $estadoEsperado !== $peticion->input('state')) {
            return response()->json([
                'mensaje' => 'El inicio de sesión no se pudo validar. Intente de nuevo.',
            ], 400);
        }

        $codigo = (string) $peticion->input('code', '');

        if ($codigo === '') {
            return response()->json([
                'mensaje' => 'El proveedor no devolvió un código de acceso.',
            ], 400);
        }

        try {
            $contenido = $this->verificador->verifica($codigo);

            $datos = $this->resolvedor->datosDelToken($contenido);

            $usuario = $this->resolvedor->resuelve($slug, $datos['identificador'], $datos['correo']);
        } catch (RuntimeException $e) {
            // El motivo se registra en el registro de la aplicación y a la
            // persona se le da un texto corto: los detalles del proveedor no se
            // muestran en pantalla.
            report($e);

            return response()->json([
                'mensaje' => 'No se pudo iniciar sesión. Pida ayuda a la dirección de la escuela.',
            ], 403);
        }

        auth()->guard('web')->login($usuario, true);

        // Se renueva el identificador de sesión después de abrirla. Es la
        // protección contra el secuestro de sesión.
        $peticion->session()->regenerate();

        return response()->json([
            'mensaje' => 'Sesión iniciada.',
            'redirigir_a' => $redirigir,
        ]);
    }

    /** Cierra la sesión. */
    public function salir(Request $peticion): JsonResponse
    {
        auth()->guard('web')->logout();

        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return response()->json([
            'mensaje' => 'Sesión cerrada.',
        ]);
    }

    /** Datos de la sesión actual. Lo que consume `useSesion.ts`. */
    public function yo(Request $peticion): JsonResponse
    {
        $usuario = $peticion->user();

        if ($usuario === null) {
            return response()->json(['usuario' => null]);
        }

        return response()->json([
            'usuario' => [
                'id' => $usuario->id,
                'nombre_completo' => $usuario->nombre_completo,
                'correo' => $usuario->correo,
                'rol' => $usuario->rol?->value,
                'rol_texto' => $usuario->rol?->etiqueta(),
                'administra' => $usuario->administra(),
                'registra_asistencia' => $usuario->registraAsistencia(),
                've_pagos' => $usuario->vePagos(),
            ],
        ]);
    }

    /**
     * Solo rutas internas como destino.
     *
     * Una dirección completa, o algo que empiece con `//`, convertiría el
     * ingreso en una redirección abierta: el atacante manda a la víctima a un
     * sitio falso justo después de iniciar sesión.
     *
     * El proxy repite la comprobación (ver `web/server/api/[...ruta].ts`).
     * Las dos capas no se estorban.
     */
    private function destinoSeguro(mixed $candidato): string
    {
        if (! is_string($candidato) || $candidato === '') {
            return '/';
        }

        if (! str_starts_with($candidato, '/')) {
            return '/';
        }

        if (str_starts_with($candidato, '//') || str_starts_with($candidato, '/\\')) {
            return '/';
        }

        return $candidato;
    }
}