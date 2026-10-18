<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Escuela;
use App\Models\Usuario;
use App\Services\Tenancy\ContextoEscuela;
use RuntimeException;
use stdClass;

/**
 * Concilia la identidad que entrega Authentik con una cuenta de `usuarios`.
 *
 * NINGUNA CUENTA SE CREA AL VOLAR
 * -------------------------------
 * Si el correo del proveedor no está dado de alta, el ingreso se rechaza. No se
 * crea una cuenta nueva a partir de un correo que llegó por internet: eso
 * permitiría que cualquiera que controlara un buzón entrara con el rol que le
 * tocara.
 *
 * El alta de cuentas es una tarea de la dirección de la escuela, y se hace en
 * pantalla.
 *
 * LA CONSULTA DE LA ESCUELA USA LA VISTA PÚBLICA
 * ----------------------------------------------
 * Para ubicar la escuela se lee `vw_escuelas_publicas`, que expone solo `slug`,
 * `nombre` y `activo`. La tabla `escuelas` completa tiene razón social y RUT que
 * no tienen por qué viajar en una petición de ingreso.
 *
 * POR QUÉ `ejecutarAislado` Y NO UNA CONSULTA NORMAL
 * -------------------------------------------------
 * En el callback todavía no hay sesión: `usuarios` tiene Row Level Security y su
 * política llama a `public.app_tenant_id()`, que lanza excepción cuando no hay
 * contexto. Por eso la lectura del usuario va dentro de
 * `ContextoEscuela::ejecutarAislado()`: fija el contexto de la escuela ya
 * identificada y recién entonces se puede leer su propia tabla de usuarios.
 */
class ResolvedorDeCuenta
{
    public function __construct(private readonly ContextoEscuela $contexto) {}

    /**
     * Devuelve el usuario de la escuela indicada, o lanza excepción.
     *
     * @throws RuntimeException si no hay cuenta activa para esa identidad.
     */
    public function resuelve(string $escuelaSlug, string $identificadorProveedor, string $correo): Usuario
    {
        // La tabla `escuelas` no tiene RLS: se lee a través de la vista, que es
        // la compensación documentada en docs/03, sección 4.
        $escuela = Escuela::query()
            ->join('vw_escuelas_publicas as vista', 'vista.slug', '=', 'escuelas.slug')
            ->where('vista.slug', $escuelaSlug)
            ->select('escuelas.*')
            ->first();

        if ($escuela === null) {
            throw new RuntimeException('La escuela indicada no existe o está inactiva.');
        }

        $usuario = $this->contexto->ejecutarAislado(
            $escuela->id,
            static fn (): ?Usuario => Usuario::query()
                ->where('proveedor_identificador', $identificadorProveedor)
                ->where('correo', $correo)
                ->first()
        );

        if ($usuario === null) {
            throw new RuntimeException(
                'No hay una cuenta dada de alta para ese correo en esta escuela.'
            );
        }

        if (! $usuario->activo) {
            throw new RuntimeException('La cuenta está desactivada. Pida a la dirección que la reactive.');
        }

        if ($usuario->tenant_id !== $escuela->id) {
            // Dos cuentas con el mismo correo en escuelas distintas son dos
            // personas distintas. El ingreso quedó con la escuela equivocada y lo
            // correcto es rechazarlo, no "corregir" la cuenta en silencio.
            throw new RuntimeException('Esta cuenta no pertenece a la escuela indicada.');
        }

        return $usuario;
    }

    /**
     * Saca del token de identidad los dos datos que se necesitan: el
     * identificador estable y el correo.
     *
     * @return array{identificador: string, correo: string}
     */
    public function datosDelToken(stdClass $contenido): array
    {
        $claimId = (string) config('authentik.claim_identificador', 'sub');
        $claimCorreo = (string) config('authentik.claim_correo', 'email');

        $identificador = $contenido->{$claimId} ?? null;
        $correo = $contenido->{$claimCorreo} ?? null;

        if (! is_string($identificador) || $identificador === '') {
            throw new RuntimeException('El token de identidad no trae identificador de usuario.');
        }

        if (! is_string($correo) || $correo === '') {
            throw new RuntimeException('El token de identidad no trae correo.');
        }

        return [
            'identificador' => $identificador,
            'correo' => strtolower(trim($correo)),
        ];
    }
}