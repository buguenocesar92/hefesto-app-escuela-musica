<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

/**
 * Verificación del token de identidad que entrega Authentik.
 *
 * QUÉ SE ACEPTA Y QUÉ NO
 * ---------------------
 * Solo el flujo de código de autorización (`code`), no el implícito. El
 * implícito entrega el token en la URL del navegador, y eso lo deja en el
 * historial y en los registros del proxy.
 *
 * La firma se verifica contra el JWKS del proveedor, y también se revisa
 * `iss` y `aud`. Verificar la firma sin mirar el emisor sirve para confirmar
 * que el token lo firmó la llave correcta, que no es lo mismo que confirmar que
 * el token era para esta aplicación.
 *
 * `verificar_firma` se puede apagar por configuración porque en desarrollo,
 * con un Authentik de pruebas, es una molestia. En producción no: si está
 * apagado, cualquiera que alcance el callback podría presentar un token hecho a
 * mano e identificarse como quien quiera. Ver docs/07.
 */
class VerificadorIdToken
{
    public function __construct(private readonly DescubridorOidc $descubridor) {}

    /**
     * Intercambia el código por tokens y devuelve el contenido del token de
     * identidad ya verificado.
     */
    public function verifica(string $codigo): stdClass
    {
        $metadatos = $this->descubridor->descubre();

        $respuesta = Http::timeout((int) config('authentik.http.timeout', 10))
            ->withOptions([
                'verify' => (bool) config('authentik.http.verificar_certificado', true),
            ])
            ->asForm()
            ->post($metadatos['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'code' => $codigo,
                'redirect_uri' => (string) config('authentik.redirect_uri'),
                'client_id' => (string) config('authentik.client_id'),
                'client_secret' => (string) config('authentik.client_secret'),
            ]);

        if (! $respuesta->successful()) {
            throw new RuntimeException('No se pudo canjear el código de inicio de sesión.');
        }

        $datos = $respuesta->json();

        if (! is_array($datos) || ! isset($datos['id_token']) || ! is_string($datos['id_token'])) {
            throw new RuntimeException('El proveedor no devolvió un token de identidad.');
        }

        $idToken = $datos['id_token'];

        $tolerancia = (int) config('authentik.tolerancia_reloj', 60);

        // Se pasa el JWKS en la forma que espera la librería, que lo resuelve
        // internamente con la llave que corresponda al `kid` del token.
        $llaves = JWK::parseKeySet($this->descargaJwks($metadatos['jwks_uri']));

        $contenido = JWT::decode($idToken, $llaves);

        $this->revisaEmisorYAudiencia($contenido, $metadatos['issuer']);

        return $contenido;
    }

    /**
     * @return array<string, mixed>
     */
    private function descargaJwks(string $url): array
    {
        $respuesta = Http::timeout((int) config('authentik.http.timeout', 10))
            ->withOptions([
                'verify' => (bool) config('authentik.http.verificar_certificado', true),
            ])
            ->get($url);

        if (! $respuesta->successful()) {
            throw new RuntimeException('No se pudieron obtener las llaves públicas del proveedor.');
        }

        $llaves = $respuesta->json();

        if (! is_array($llaves) || ! isset($llaves['keys'])) {
            throw new RuntimeException('El conjunto de llaves del proveedor viene vacío.');
        }

        return $llaves;
    }

    /**
     * Revisa que el token sea para esta aplicación y venga del proveedor
     * esperado.
     */
    private function revisaEmisorYAudiencia(stdClass $contenido, string $emisorEsperado): void
    {
        $issuer = config('authentik.issuer') ?? $emisorEsperado;

        if (($contenido->iss ?? null) !== $issuer) {
            throw new RuntimeException('El token de identidad viene de un emisor distinto del configurado.');
        }

        $audiencia = config('authentik.client_id');

        // `aud` puede venir como cadena o como arreglo según el proveedor.
        $aud = $contenido->aud ?? null;
        $coincide = is_string($aud)
            ? hash_equals((string) $audiencia, $aud)
            : (is_array($aud) && in_array((string) $audiencia, $aud, true));

        if (! $coincide) {
            throw new RuntimeException('El token de identidad no es para esta aplicación.');
        }
    }
}