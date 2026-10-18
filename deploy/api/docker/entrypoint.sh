#!/bin/sh
# ---------------------------------------------------------------------------
# Entrada del contenedor de la API
# ---------------------------------------------------------------------------
# Arranca el servidor de trabajos en segundo plano, corre los guiones de
# arranque y deja el proceso principal en manos de supervisor.
#
# Un momento de tentación sería poner aquí el primer ingreso de una escuela de
# ejemplo. No se pone: una escuela de ejemplo que alguien olvidó borrar aparece
# en el selector de ingreso de producción. La escuela real se crea con
# `php artisan escuela-musica:crear`.
# ---------------------------------------------------------------------------

set -e

echo "[entrada] esperando a PostgreSQL en ${DB_HOST}:${DB_PORT}"

# Espera activa, con un tope. Si la base no llega, es preferible fallar al
# arrancar: entrar sin base y fallar en cada petición da errores que parecen
# de la aplicación.
intentos=0
maximo=30

while ! pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-escuela_musica_app}" -q; do
    intentos=$((intentos + 1))

    if [ "$intentos" -ge "$maximo" ]; then
        echo "[entrada] la base de datos no respondió después de ${maximo} intentos."
        echo "[entrada] revise DB_HOST, DB_PORT y las credenciales en el archivo .env."
        exit 1
    fi

    sleep 2
done

echo "[entrada] PostgreSQL disponible."

echo "[entrada] limpiando lo cacheado..."
php artisan config:clear --quiet || true
php artisan route:clear --quiet || true
php artisan view:clear --quiet || true

# Las migraciones se aplican al arrancar. Es una decisión consciente para
# despliegues de una sola aplicación: el proceso de despliegue se encarga de
# revisar el código antes de reiniciar.
#
# Con `--force` porque en producción `APP_ENV=production` hace que Laravel pida
# confirmación interactiva, y un contenedor no tiene quién responda.
echo "[entrada] aplicando migraciones..."
php artisan migrate --force --no-interaction

# La caché de configuración se hornea después de las migraciones para que
# cualquier valor que las migraciones toquen quede fijado.
php artisan config:cache --quiet
php artisan route:cache --quiet

echo "[entrada] listo. Iniciando supervisor."

exec supervisord -c /etc/supervisor/supervisord.conf -n