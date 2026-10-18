<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Escuela;
use App\Models\Usuario;
use Illuminate\Console\Command;

/**
 * Alta de la primera escuela y de su primera cuenta de dirección.
 *
 * POR QUÉ ESTE COMANDO EXISTE
 * ---------------------------
 * La tabla `escuelas` no tiene Row Level Security porque se consulta cuando
 * todavía no hay contexto de escuela. Crear una escuela desde la consola es la
 * única forma de hacerlo sin saltarse esa protección a mano.
 *
 * PIDE TODOS LOS DATOS QUE NO SON INVENTABLES
 * -------------------------------------------
 * El nombre de la escuela y el slug se preguntan, no se inventan. No hay valores
 * por omisión: una escuela de ejemplo que alguien olvide borrar termina
 * apareciendo en el selector de ingreso de producción.
 */
class CrearEscuelaCommand extends Command
{
    protected $signature = 'escuela-musica:crear
                            {--nombre= : Nombre de la escuela}
                            {--slug= : Identificador corto, en minúsculas y sin espacios}
                            {--razon-social= : Razón social, si emite factura}
                            {--rut= : RUT de la escuela, si emite factura}
                            {--admin-correo= : Correo de la primera cuenta de dirección}
                            {--admin-nombre= : Nombre de la primera cuenta de dirección}
                            {--proveedor-identificador= : Identificador del usuario en Authentik}';

    protected $description = 'Crea una escuela y su primera cuenta de dirección.';

    public function handle(): int
    {
        $slug = (string) ($this->option('slug') ?? $this->pregunta('Identificador corto (slug)', 'escuela'));
        $slug = $this->normalizaSlug($slug);

        if (Escuela::query()->where('slug', $slug)->exists()) {
            $this->error("Ya existe una escuela con el identificador {$slug}.");

            return self::FAILURE;
        }

        $nombre = (string) ($this->option('nombre') ?? $this->pregunta('Nombre de la escuela', $slug));

        $escuela = Escuela::query()->create([
            'slug' => $slug,
            'nombre' => $nombre,
            'razon_social' => $this->opcionVaciaANull($this->option('razon-social')),
            'rut' => $this->opcionVaciaANull($this->option('rut')),
            'activo' => true,
        ]);

        $this->info("Escuela creada: {$escuela->nombre} ({$escuela->slug})");

        $correo = $this->opcionVaciaANull($this->option('admin-correo'));
        $nombreUsuario = $this->opcionVaciaANull($this->option('admin-nombre'));
        $identificador = $this->opcionVaciaANull($this->option('proveedor-identificador'));

        if ($correo === null || $nombreUsuario === null || $identificador === null) {
            $this->warn('No se creó ninguna cuenta: falta el correo, el nombre o el identificador del proveedor.');
            $this->line('');
            $this->line('Cree la cuenta de dirección desde la consola o desde la aplicación');
            $this->line('una vez que la escuela exista. No hay un comando aparte porque');
            $this->line('necesita el identificador que entrega Authentik.');

            return self::SUCCESS;
        }

        $usuario = Usuario::query()->create([
            'tenant_id' => $escuela->id,
            'proveedor' => 'authentik',
            'proveedor_identificador' => $identificador,
            'correo' => $correo,
            'nombre_completo' => $nombreUsuario,
            'rol' => 'direccion',
            'activo' => true,
        ]);

        $this->info("Cuenta de dirección creada: {$usuario->correo}");

        return self::SUCCESS;
    }

    private function pregunta(string $texto, string $porOmision): string
    {
        $respuesta = (string) $this->ask($texto, $porOmision);

        return trim($respuesta);
    }

    /**
     * Minúsculas y sin espacios: el slug aparece en la URL del ingreso.
     */
    private function normalizaSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function opcionVaciaANull(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}