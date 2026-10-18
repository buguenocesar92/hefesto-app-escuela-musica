# 04. Instalación

**Nada de esto se ha ejecutado.** No hay registro de una instalación real. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).

---

## Requisitos

| Componente | Versión | Por qué esa |
|---|---|---|
| Linux con Docker y Docker Compose v2 | 24.04 o superior | Es el destino de despliegue |
| Node.js | 20.19 o superior | Lo exige el `package.json` |
| PHP | 8.3 o superior | Lo exige `composer.json`, para compilar la interfaz |
| Composer | 2.x | Para instalar la API |
| PostgreSQL | 16 | **Obligatorio.** La Row Level Security no existe en otro motor |

### Por qué PostgreSQL no es negociable

El aislamiento entre escuelas está implementado con Row Level Security, que es
una función de PostgreSQL. Con SQLite o MySQL, las pruebas de aislamiento
pasarían sin comprobar nada: darían verde contra una base que no tiene
políticas.

Por eso `phpunit.xml` fija la conexión en `pgsql` y **no la lee del `.env`**.

---

## Instalación de la API

### 1. Generar el esqueleto de Laravel

El repositorio **no incluye** el esqueleto de Laravel: lo genera Composer. Por
eso `bootstrap/app.php` de esta aplicación reemplaza al del esqueleto estándar,
para registrar el middleware y el manejo de errores.

```bash
# En un directorio temporal, fuera del repositorio
composer create-project laravel/laravel /tmp/esqueleto --no-interaction

# Copie encima los archivos de la aplicación
cp -a /tmp/esqueleto/. apps/escuela-musica/api/

# Y después vuelva a poner los archivos propios encima, que son los que ganan
# en caso de coincidencia:
# - bootstrap/app.php
# - config/
# - database/migrations/
# - routes/
# - app/
# - tests/
```

Los archivos que la aplicación **no** tiene (artisan, `public/index.php`,
`storage/`) se conservan del esqueleto, que es justo lo que se necesita para
que funcione.

### 2. Dependencias

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Variables de entorno

```bash
cp .env.example .env
php artisan key:generate
```

`api/.env.example` viene con las tres reglas de negocio **vacías**, y eso es lo
correcto. Ver [05-CONFIGURACION.md](05-CONFIGURACION.md).

### 4. Migraciones

```bash
php artisan migrate --force
```

El orden importa y lo fija la marca de tiempo de cada archivo:

```
000001  escuelas
000002  docentes
000003  usuarios
000004  salas
000005  alumnos
000006  responsables
000007  alumno_responsables
000008  planes
000009  inscripciones
000010  pagos
000011  clases
000012  inscripciones_clase
000013  asistencias
000014  recuperaciones
000015  movimientos_clase_plan
000016  instrumentos
000017  prestamos_instrumento
000018  bitacora_accesos
000019  RLS          <- el corazón del producto
000020  sessions
000021  la vista publica
```

`usuarios` va después de `docentes` porque lo referencia con una clave foránea.

### 5. Verificar el aislamiento

```bash
php artisan escuela-musica:verificar-aislamiento
```

Si dice que el aislamiento es correcto, la Row Level Security está activa y el
rol de la conexión no la está saltando. Si falla, **no siga** con el paso 6:
una escuela vería los datos de todas las demás.

### 6. La primera escuela

```bash
php artisan escuela-musica:crear \
    --nombre="Escuela de Música Los Aromos" \
    --slug="escuela-aromos" \
    --admin-correo="direccion@ejemplo.cl" \
    --admin-nombre="Persona a Cargo" \
    --proveedor-identificador="IDENTIFICADOR-DE-AUTHENTIK"
```

No hay valores por omisión para el nombre ni el identificador porque no se
pueden inventar: un nombre de ejemplo que alguien olvide borrar aparece en el
selector de ingreso de producción.

### 7. Sembrar datos de demostración

Solo en desarrollo, y el seeder **se niega a correr en producción**:

```bash
php artisan db:seed
```

Crea una escuela de ejemplo con docentes, salas, alumnos e instrumentos. **No
crea precios ni plazos**: son decisiones de la escuela.

---

## Instalación de la interfaz

```bash
cd apps/escuela-musica/web

cp .env.example .env
npm install
npm run build
```

La interfaz es un cliente de la API a través del proxy, así que depende de que
`NUXT_API_BASE` apunte a la dirección interna de la API.

---

## Despliegue con Docker

### Desarrollo

```bash
cd apps/escuela-musica/deploy
cp ../../.env.example .env
docker compose -f docker-compose.dev.yml up -d
```

En desarrollo se publican PostgreSQL (5432), Redis (6379), la API (8080) y la
interfaz (3000), **acotados a `127.0.0.1`**.

### Producción

```bash
cd apps/escuela-musica/deploy
cp ../../.env.example .env.prod
```

Complete en `.env.prod` al menos:

| Variable | Por qué es obligatoria |
|---|---|
| `DOMINIO` | El proxy lo necesita y no hay un valor por omisión sensato |
| `ACME_CORREO` | Sin él nadie recibe el aviso de caducidad del certificado |
| `APP_KEY` | Sin ella no hay sesión |
| `DB_PASSWORD` | Sin ella PostgreSQL rechaza conexiones |
| `AUTHENTIK_*` | Sin ellos no hay ingreso |

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

### Lo único que pública puertos

En `docker-compose.prod.yml`, el **único** servicio con sección `ports:` es el
proxy:

```yaml
  proxy:
    ports:
      - "80:80"
      - "443:443"
```

PostgreSQL, Redis, la API y la interfaz **no** tienen `ports:`. Lo que no se
pública no se alcanza desde internet.

Comprobarlo:

```bash
docker compose -f docker-compose.prod.yml ps
```

Solo el proxy debe aparecer con un puerto en la columna `PORTS`.

### El dominio antes de arrancar

`deploy/caddy/Caddyfile` tiene `{DOMINIO}` como marcador. Hay que reemplazarlo:

```bash
sed -i "s/{DOMINIO}/escuela-ejemplo.cl/g" caddy/Caddyfile
```

No se puso un dominio de ejemplo en el archivo porque un dominio inventado en
producción falla de forma difícil de diagnosticar: el certificado nunca se emite
y el error apunta a la red, no a la configuración.

---

## Comprobaciones después del despliegue

```bash
# 1. El aislamiento
docker compose -f docker-compose.prod.yml run --rm api \
    php artisan escuela-musica:verificar-aislamiento

# 2. La salud del proxy
curl -sS https://DOMINIO/salud

# 3. Que el selector de ingreso existe y trae escuelas
curl -sS https://DOMINIO/api/v1/auth/escuelas

# 4. Que una ruta de negocio sin sesión NO abre
curl -sS -o /dev/null -w '%{http_code}\n' https://DOMINIO/api/v1/alumnos
```

El paso 4 debe responder **401**. Si respondiera 200, no habría sesión que se
respetara.

---

## Problemas que se anticipan

| Síntoma | Causa probable |
|---|---|
| `app_tenant_id` lanza excepción en la primera consulta | Falta el middleware. En Laravel 11 o superior, verificar que `bootstrap/app.php` tenga el `ResolverTenant` en el grupo `web` |
| El verificador dice que una tabla no tiene RLS | La migración 000019 corrió antes que la de esa tabla |
| El verificador dice que el rol es superusuario | El `POSTGRES_USER` de producción coincide con `postgres`. Hay que crear un rol propio |
| El ingreso vuelve del proveedor y dice que no hay cuenta | No hay fila en `usuarios` con ese correo, o la escuela elegida no corresponde a la cuenta |
| `pg_isready` no encuentra la base | Falta `pg_isready` en la imagen, o las credenciales no coinciden |

---

## Lo que quedó pendiente de verificar

| Punto | Por qué |
|---|---|
| Que el esqueleto de Laravel que genera Composer coincida con lo que espera `bootstrap/app.php` | No había PHP ni Composer |
| Que las imágenes base existan en el registro del cliente | No había Docker |
| Que `btree_gist` esté disponible, si más adelante se quiere la restricción de exclusión de la agenda | No había PostgreSQL |
| El dominio y el certificado | Dependen del cliente |