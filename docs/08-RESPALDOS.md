# 08. Respaldos

## Advertencia honesta

**Los dos scripts de este directorio no se ejecutaron.** En el entorno donde se
escribió el código no había PostgreSQL ni Bash de producción. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).

Antes de confiar en ellos, hay que probarlos sobre una base de datos descartable.

---

## Las cinco reglas

Estas reglas no son preferencias. Vienen de errores reales en proyectos
anteriores, en los que un respaldo resulto no ser restaurable, o una
restauración terminó a medias, o un `DROP` dejó la base sin estructura.

### 1. Manifiesto con la suma SHA-256 de cada archivo

`respaldo.sh` escribe `manifiesto.sha256` con la suma de verificación de cada
archivo, y **comprueba esa suma recién escrita**.

Comprobar el archivo que se acaba de escribir y no una versión en memoria es lo
que detecta un disco que falla o una transferencia que se corrompe.

```bash
cd /var/backups/escuela-musica/respaldo-20260310-023000
sha256sum --check manifiesto.sha256
```

Un respaldo del que nadie puede verificar la integridad es una caja cerrada.

### 2. El respaldo se marca como completo SOLO al final

El archivo `COMPLETO` se escribe **al final de todo**, después del volcado, del
manifiesto, de la comprobación de la suma y de los metadatos.

Si el proceso se interrumpe antes, el directorio queda sin ese archivo. Es
deliberado: un respaldo a medio hacer se identifica solo.

Un respaldo que **parece** completo y no lo está es peor que no tener respaldo,
porque da una falsa tranquilidad.

### 3. Se validan las rutas y el espacio antes de escribir

- El directorio de destino tiene que **existir antes**. Crearlo a mitad de camino
  es como se termina un respaldo dentro de una partición llena.
- Se mide el tamaño de la base y se exige al menos el doble de espacio libre.
- Si el destino está ocupado, el script **para**.

### 4. Nunca se sobrescribe nada

Si el directorio ya existe, `respaldo.sh` se detiene:

```
ERROR: Ya existe /var/backups/escuela-musica/respaldo-20260310-023000.
ERROR: No se sobrescribe nada. Use --force para escribir en otro directorio.
```

Con `--force` **tampoco** sobrescribe: escribe en un directorio nuevo, con el
sufijo numérico agregado.

```bash
./respaldo.sh                    # falla si ya existe
./respaldo.sh --force            # escribe en respaldo-...-1, y deja el otro intacto
```

### 5. Nunca se usa DROP, y el servicio queda detenido

En `restauracion.sh`:

- Se limpia con **TRUNCATE**, nunca con `DROP`. `DROP` borra los permisos, las
  extensiones instaladas y la configuración, y reconstruirla exige saber qué
  había. `TRUNCATE` deja la base exactamente como estaba, menos los datos.
- El servicio queda **detenido** al terminar.

Que quede detenido es contraintuitivo y es lo correcto: la aplicación tiene en
caché y en sesión datos que ya no corresponden con la base restaurada. Arrancarla
enseguida muestra alumnos que ya no existen.

---

## Respaldar

```bash
cd apps/escuela-musica/deploy
./respaldos/respaldo.sh
```

Produce:

```
/var/backups/escuela-musica/respaldo-20260310-023000/
  base.dump        el volcado de PostgreSQL, en formato personalizado
  manifiesto.sha256  la suma de verificación de base.dump
  metadatos.txt    fecha, base de datos, servidor, versión de pg_dump
  COMPLETO         la marca de que terminó bien
```

`pg_dump` se usa con `--format=custom`, que permite restaurar tablas
seleccionadas y comprime mejor que el formato de texto plano.

### Configurar el destino

```bash
export DESTINO_BASE=/var/backups/escuela-musica
mkdir -p "$DESTINO_BASE"
chmod 700 "$DESTINO_BASE"
```

El destino tiene que estar **fuera** del servidor, en un disco o volumen
distinto. Un respaldo en el mismo disco del que sale no es un respaldo.

### Automatizar

```cron
# A las 2 de la mañana, de domingo a sábado
0 2 * * * cd /opt/escuela-musica/deploy && ./respaldos/respaldo.sh >> /var/log/escuela-respaldo.log 2>&1
```

Y conviene una copia fuera del servidor:

```cron
# A las 4 de la mañana, copiar los respaldos de los últimos siete días
0 4 * * * rsync -a --delete /var/backups/escuela-musica/ usuario@respaldo:/respaldos/escuela-musica/
```

---

## Restaurar

```bash
cd apps/escuela-musica/deploy
./respaldos/restauracion.sh /var/backups/escuela-musica/respaldo-20260310-023000
```

Pide escribir `RESTAURAR` en mayúsculas, salvo que se pase `--force`.

### Lo que hace, en orden

1. **Comprueba que el respaldo esté completo.** Si falta el archivo `COMPLETO`,
   para sin tocar la base.
2. **Verifica el manifiesto.** Si las sumas no coinciden, para.
3. **Muestra el riesgo** y pide confirmación.
4. **Detiene la aplicación**: web, proxy, api, cola y planificador. Antes de tocar
   la base, no después.
5. **Espera** a que PostgreSQL acepte conexiones.
6. **Limpia con TRUNCATE**, nunca con DROP.
7. **Restaura** con `pg_restore --single-transaction`.
8. **Deja el servicio detenido** y dice cómo arrancarlo.

### Si algo falla a mitad

Con `--single-transaction`, si algo falla **no queda nada a medias**: la base
vuelve al estado anterior. Eso es justamente para lo que está la opción.

El script lo dice con todas las palabras:

```
ERROR: La restauración falló y se revirtió por completo.
ERROR: La base quedó como estaba antes de empezar.
ERROR: El servicio sigue DETENIDO.
```

### Después de restaurar

```bash
# 1. Comprobar el aislamiento ANTES de recibir gente
docker compose -f docker-compose.prod.yml run --rm api \
    php artisan escuela-musica:verificar-aislamiento

# 2. Revisar que los datos están
docker compose -f docker-compose.prod.yml run --rm api \
    php artisan escuela-musica:revisar-morosidad

# 3. Arrancar
docker compose -f docker-compose.prod.yml start api web proxy
```

---

## Una prueba de restauración

Un respaldo que nunca se restauró no es un respaldo: es una suposición.

Una vez al mes, sobre una base **descartable**:

```bash
# 1. Respaldar
./respaldos/respaldo.sh

# 2. Crear un alumno de prueba y verificar que existe
docker compose -f docker-compose.prod.yml exec postgres \
    psql -U escuela_musica_app -d escuela_musica -c "\dt"

# 3. Restaurar el respaldo sobre esa misma base

# 4. Comprobar que el estado es el del respaldo y no el de después
```

Si la escuela tiene suficientes estudiantes, la recomendación más honesta es
ensayar en una instalación de pruebas y **no tocar producción sin haber probado
antes**.

---

## Lo que se respaldó y lo que no

| Se respalda | No se respalda | Por qué |
|---|---|---|
| La base de datos | Los archivos de la interfaz | Se reconstruyen con `npm run build` |
| | Los documentos de autorización escaneados | **Están fuera del respaldo.** Ver la nota siguiente |
| | El almacenamiento de la sesión | No vale la pena: se pierde la sesión al restaurar |
| | Redis | Es caché. Si se pierde, la aplicación sigue funcionando |

### La nota sobre los documentos de autorización

`api/.gitignore` excluye `/storage/app/documentos/`.

Si la escuela escanea los documentos firmados, **no entran en el respaldo** que
hace este script, porque el script solo respalda la base de datos.

Eso es una limitación real y hay que resolverla antes de producción: o se
respalda ese directorio por separado, o se sube a un almacenamiento externo con
su propio respaldo. `alumno_responsables.autorizacion_documento` guarda el texto
de la referencia, no el archivo.