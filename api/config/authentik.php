<?php

declare(strict_types=1);

return [

    /*
    |-----------------------------------------------------------------------
    | Proveedor OIDC (Authentik)
    |-----------------------------------------------------------------------
    |
    | La aplicación NO implementa el login: delega en Authentik y guarda lo que
    | devuelve. No hay contraseñas, no hay formularios propios y no se guardan
    | tokens de terceros más de lo necesario.
    |
    | Lo que se guarda del proveedor es lo mínimo: el identificador del
    | proveedor, para poder reencontrar la cuenta en el próximo ingreso, y el
    | correo, que es el dato con el que se concilia la cuenta con `usuarios`.
    |
    | NOTA SOBRE EL VALOR POR OMISIÓN DEL PUERTO
    | -----------------------------------------
    | 8443 es el puerto que publica Authentik en `docker-compose.prod.yml` de
    | este mismo repositorio. No es un valor inventado, pero conviene revisarlo
    | si la instalación cambia de kit. Ver docs/05-CONFIGURACION.md.
    |
    */

    'url' => env('AUTHENTIK_BASE_URL'),

    'issuer' => env('AUTHENTIK_ISSUER'),

    'client_id' => env('AUTHENTIK_CLIENT_ID'),

    'client_secret' => env('AUTHENTIK_CLIENT_SECRET'),

    'redirect_uri' => env('AUTHENTIK_REDIRECT_URI'),

    'scope' => env('AUTHENTIK_SCOPE', 'openid profile email'),

    /*
    | Claim del que se lee el identificador estable de la persona. Authentik
    | entrega el identificador del usuario en el claim estándar `sub`.
    */
    'claim_identificador' => env('AUTHENTIK_CLAIM_IDENTIFICADOR', 'sub'),

    'claim_correo' => env('AUTHENTIK_CLAIM_CORREO', 'email'),

    'scopes_por_defecto' => ['openid', 'profile', 'email'],

    'codigos_respuesta_validos' => ['code'],

    /*
    | Cuando es `true` se exige que el token de ID venga firmado. No se puede
    | apagar en producción: si está apagado, cualquiera que alcance el callback
    | podría presentar un token hecho a mano e identificarse como quien quiera.
    |
    */
    'verificar_firma' => (bool) env('AUTHENTIK_VERIFICAR_FIRMA', true),

    /*
    | Tolerancia de reloj al validar `exp` y `nbf`, en segundos. Existe porque
    | los relojes entre el proveedor y esta máquina no son idénticos.
    */
    'tolerancia_reloj' => (int) env('AUTHENTIK_TOLERANCIA_RELOJ', 60),

    'http' => [
        'verificar_certificado' => (bool) env('AUTHENTIK_VERIFICAR_CERTIFICADO', true),
        'timeout' => (int) env('AUTHENTIK_HTTP_TIMEOUT', 10),
    ],

];