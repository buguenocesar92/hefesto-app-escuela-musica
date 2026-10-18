# ---------------------------------------------------------------------------
# Imagen de la API (Laravel + PHP-FPM + nginx)
# ---------------------------------------------------------------------------
# VERIFICACIÓN PENDIENTE
# ----------------------
# Esta imagen NO se construyó. En la máquina donde se escribió el código no
# había Docker. La etiqueta `php:8.3-fpm-bookworm` es la misma que usan las demás
# aplicaciones de Hefesto para este propósito, pero no se comprobó que esté
# disponible en el registro del cliente. Ver docs/10-PRUEBAS-Y-ESTADO.md.
# ---------------------------------------------------------------------------

FROM php:8.3-fpm-bookworm

# Las extensiones que la aplicación necesita:
#   - pgsql: la conexión a PostgreSQL, que es obligatoria (Row Level Security).
#   - pdo_pgsql: el driver de PDO que usa Laravel.
#   - mbstring, bcmath, intl, zip: las exige Laravel y sus dependencias.
#   - opcache: rendimiento; sin él PHP recompila todo en cada petición.
ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libonig-dev \
        supervisor \
        nginx \
        ca-certificates \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        intl \
        mbstring \
        bcmath \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# Composer, desde la imagen oficial. No se instala con el gestor de paquetes de
# Debian porque la versión que trae el repositorio suele estar atrasada.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Se copia primero el manifiesto para que la instalación de dependencias quede
# en la capa de Docker y se reutilice mientras el manifiesto no cambie.
COPY composer.json ./

# Sin `--no-scripts`: el proyecto no tiene un esqueleto instalado todavía y los
# guiones de Composer fallarían al no existir `artisan`.
RUN composer install \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/escuela-musica.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/escuela-musica.conf

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]