<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Descubrimiento del proveedor OIDC.
 *
 * Solo se usa al inicio de sesión: devuelve el authorization endpoint y el JWKS
 * que usa `VerificadorIdToken`. No se guarda en caché a propósito: son un
 * par de llamadas por ingreso y una caché desactualizada es una forma
 * silenciosa de romper el acceso.
 */
class DescubridorOidc
{
    /**
     * Configuración del proveedor, tal como la entrega el descubrimiento.
     *
     * @return array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string, issuer: string}
     */
    public function descubre(): array
    {
        $base = (string) config('authentik.url');

        if ($base === '') {
            throw new RuntimeException(
                'AUTHENTIK_BASE_URL no está configurado. No se puede iniciar sesión.'
            );
        }

        // El issuer de Authentik apunta al final del flujo; el documento de
        // descubrimiento cuelga de ahí.
        $url = rtrim($base, '/') . '/.well-known/openid-configuration';

        $respuesta = Http::timeout((int) config('authentik.http.timeout', 10))
            ->withOptions([
                'verify' => (bool) config('authentik.http.verificar_certificado', true),
            ])
            ->get($url);

        if (! $respuesta->successful()) {
            throw new RuntimeException(
                'El proveedor de identidad no respondió al documento de descubrimiento.'
            );
        }

        $datos = $respuesta->json();

        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'issuer'] as $campo) {
            if (! is_array($datos) || ! isset($datos[$campo]) || ! is_string($datos[$campo])) {
                throw new RuntimeException(
                    'El documento de descubrimiento del proveedor no trae ' . $campo . '.'
                );
            }
        }

        return [
            'authorization_endpoint' => $datos['authorization_endpoint'],
            'token_endpoint' => $datos['token_endpoint'],
            'jwks_uri' => $datos['jwks_uri'],
            'issuer' => $datos['issuer'],
        ];
    }

    /** URL de la pantalla de autorización de Authentik. */
    public function urlDeAutorizacion(): string
    {
        return $this->descubre()['authorization_endpoint'];
    }
}