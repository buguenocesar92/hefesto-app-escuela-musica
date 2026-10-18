<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RolUsuario;
use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuario: quién entra al sistema.
 *
 * Extiende `Authenticatable` porque es lo que el guard de sesión acepta.
 *
 * SIN CONTRASEÑA, y no por descuido: el ingreso es OIDC contra Authentik. Este
 * modelo guarda el identificador que entrega el proveedor para reencontrar la
 * cuenta, y no guarda ninguna credencial.
 */
class Usuario extends Authenticatable
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'usuarios';

    protected $guarded = ['id'];

    protected $hidden = ['proveedor_identificador'];

    protected function casts(): array
    {
        return [
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
            'ultimo_acceso' => 'datetime',
        ];
    }

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class, 'tenant_id');
    }

    /** Vínculo opcional: la misma persona que da clases y registra su asistencia. */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id');
    }

    public function asistenciasRegistradas(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'registrado_por');
    }

    /** ¿Administra la escuela (crear y editar alumnos, planes, salas, clases)? */
    public function administra(): bool
    {
        return $this->activo && $this->tieneRol(config('escuela-musica.roles_que_administran', []));
    }

    /** ¿Puede registrar asistencia? El docente que imparte la clase, sí. */
    public function registraAsistencia(): bool
    {
        return $this->activo && $this->tieneRol(config('escuela-musica.roles_que_registran_asistencia', []));
    }

    /**
     * ¿Puede ver los pagos de los alumnos?
     *
     * Lista explícita, no "todos los que pueden entrar": un docente necesita
     * ver quién viene a su clase, no cuánto pagó la familia.
     */
    public function vePagos(): bool
    {
        return $this->activo && $this->tieneRol(config('escuela-musica.roles_que_ven_pagos', []));
    }

    /**
     * Compara el rol contra una lista de configuración.
     *
     * `rol` viene casteado a `RolUsuario`, así que se compara contra el
     * `value` del enum y no contra el texto crudo. Una lista vacía significa
     * "nadie tiene este permiso": es lo que se quiere cuando una regla queda
     * sin configurar, no "todos pueden".
     *
     * @param  list<string>  $roles
     */
    private function tieneRol(array $roles): bool
    {
        return $this->rol instanceof RolUsuario
            && in_array($this->rol->value, $roles, true);
    }

    /** Nombre corto para las cabeceras de las pantallas. */
    public function nombreCorto(): string
    {
        $partes = preg_split('/\s+/', trim($this->nombre_completo)) ?: [];

        return $partes[0] ?? '';
    }
}