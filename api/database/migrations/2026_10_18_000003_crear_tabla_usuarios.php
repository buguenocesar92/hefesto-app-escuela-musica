<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios: quién entra al sistema.
 *
 * Sin contraseñas. El ingreso es OIDC contra Authentik y la sesión la abre el
 * callback. `proveedor_identificador` guarda el identificador estable que
 * entrega el proveedor, para reencontrar la cuenta en el próximo ingreso.
 *
 * Un usuario puede tener `docente_id`: es la misma persona dando clases y
 * entrando al sistema a registrar la asistencia. Si lo es, el rol decide qué
 * puede ver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('escuelas')->cascadeOnDelete();

            $tabla->string('proveedor', 60)->default('authentik');

            // Identificador estable del usuario en el proveedor. Es el que se
            // compara al volver, NO el correo: un correo puede cambiar y la
            // cuenta seguir siendo la misma.
            $tabla->string('proveedor_identificador', 190);

            $tabla->string('correo', 160);

            $tabla->string('nombre_completo', 160);

            // Rol como texto y no tabla de roles: son pocos y no cambian.
            // Los valores admitidos están en `config/escuela-musica.php`.
            $tabla->string('rol', 40);

            // Vínculo opcional con el docente. Ver la nota de la migración de
            // `docentes` sobre por qué son dos cosas separadas.
            $tabla->foreignUuid('docente_id')->nullable()->constrained('docentes')->nullOnDelete();

            $tabla->boolean('activo')->default(true);

            $tabla->timestampTz('ultimo_acceso')->nullable();

            $tabla->timestampsTz();

            // El mismo correo puede existir en dos escuelas distintas: son
            // personas distintas en la realidad aunque compartan el buzón.
            $tabla->unique(['tenant_id', 'proveedor', 'proveedor_identificador'], 'usuarios_proveedor_identificador_unico');
            $tabla->unique(['tenant_id', 'correo'], 'usuarios_correo_unico_por_escuela');

            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};