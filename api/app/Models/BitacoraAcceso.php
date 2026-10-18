<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceAEscuela;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Bitácora de accesos.
 *
 * No tiene `timestamps`: se escribe una vez y no se edita nunca, así que
 * `created_at` y `updated_at` no aportarían nada.
 */
class BitacoraAcceso extends Model
{
    use HasUuids;
    use PerteneceAEscuela;

    protected $table = 'bitacora_accesos';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    public const CREATED_AT = 'ocurrido_en';

    protected function casts(): array
    {
        return [
            'ocurrido_en' => 'datetime',
            'resultado' => 'integer',
        ];
    }
}