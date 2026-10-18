# 10. Pruebas y estado

## El estado real

**Nada de este código se ha ejecutado.**

En la máquina donde se escribió no había PHP, ni Composer, ni Node, ni Docker,
ni PostgreSQL. En concreto:

| Componente | Cantidad |
|---|---|
| Migraciones aplicadas | **0** de 21 |
| Pruebas corridas | **0** de 37 |
| Imágenes construidas | **0** de 2 |
| Comandos de artisan ejecutados | **0** |
| Respaldos probados | **0** de 2 scripts |
| Despliegues | **0** |

Todo lo que hay en esta aplicación es **código escrito y no verificado**.

Lo único que sí se ejecutó sobre los archivos, y no sobre la aplicación, fue:

- la revisión de sintaxis de los tres guiones de shell con `bash -n`;
- el validador de aplicaciones del repositorio;
- la revisión de acentuación.

Ninguna de las tres dice algo sobre si la aplicación funciona.

---

## Las pruebas: están escritas y no se corrieron

Hay cuatro suites. Todas apuntan a PostgreSQL de verdad.

| Suite | Qué cubre | Casos |
|---|---|---|
| `Aislamiento` | Que una escuela no vea los datos de otra | 14 |
| `Agenda` | El choque de horario de docente y sala | 7 |
| `Planes` | El consumo de clases del plan | 8 |
| `Recuperacion` | La caducidad de la clase recuperada | 8 |

**Las 37 están escritas y ninguna se ejecutó.** Pueden tener errores de sintaxis,
de lógica o de estructura, y con probabilidad los tienen: código que nunca se
corrió casi nunca está bien a la primera.

### Por qué `phpunit.xml` fija la conexión en `pgsql`

```xml
<env name="DB_CONNECTION" value="pgsql"/>
```

La variable está fija y **no se lee del `.env`**, a propósito.

El aislamiento está implementado con Row Level Security, que es una función de
PostgreSQL. En SQLite, las pruebas de aislamiento pasarían sin comprobar nada:
darían verde contra una base que no tiene políticas. Una suite verde que no
comprueba lo que dice comprobar es peor que no tener suite.

### Las tres reglas de negocio salen vacías en las pruebas

En `phpunit.xml` están vacías a propósito, para que el comportamiento por omisión
del sistema sea el que se prueba primero: nadie decidió esto todavía.

Las pruebas que necesitan una regla definida la fijan dentro del caso con
`config()->set()`. Es una decisión de diseño: si el entorno las trajera puestas,
no se podría probar el estado "sin definir", que es el estado por omisión y el
que más importa conocer.

### Una decisión razonada que necesita ajuste

`tests/TestCase.php` fija el contexto de escuela con `set_config(..., false)`, es
decir con alcance de **sesión**, no de transacción.

En la aplicación real es al revés: alcance de **transacción**, para que el worker
de colas no arrastre la escuela de otra. En las pruebas, las aserciones corren
fuera de la transacción del middleware, así que si el alcance fuera de
transacción dejarían de ver el contexto.

El razonamiento es que en las pruebas cada caso abre su propia conexión y el
valor no sobrevive entre pruebas. **No se comprobó que sea así**, y puede
necesitar ajuste cuando las pruebas corran de verdad. Está anotado en el propio
`TestCase.php` para que quien lo lea no lo tome por una decisión firme.

---

## Lo que quedó a medias, y por qué

### El solapamiento de agenda no lo impone la base

La solución correcta sería una restricción de exclusión de PostgreSQL:

```sql
CREATE EXTENSION btree_gist;
ALTER TABLE clases
    ADD CONSTRAINT clases_sin_solapamiento
    EXCLUDE USING gist (
        docente_id WITH =,
        tstzrange(inicio, termina) WITH &&
    );
```

No se puso porque exige la extensión `btree_gist` y **no se pudo comprobar que
esté disponible** en el entorno de instalación.

Se prefirió una comprobación en la aplicación, escrita y con pruebas, antes que
una restricción sin verificar. La consecuencia, que hay que tener presente: **dos
personas que creen la misma clase al mismo tiempo pueden meterla**. La
comprobación de la aplicación lee, escribe y comprueba en ese orden, sin un
bloqueo. Para una PyME de una escuela eso es aceptable; en una instalación con
muchos usuarios simultáneos, no.

Si aparece el problema, la corrección es la restricción de exclusión de arriba.

### No hay pantallas de alta para docentes, salas y planes

El alta funciona por la API, pero no hay una pantalla de formularios para
crear docentes ni salas. Es una desproporción conocida, anotada en
[06-PRIMER-ALUMNO.md](06-PRIMER-ALUMNO.md).

### Los documentos de autorización no entran en el respaldo

`respaldo.sh` solo respalda la base de datos. Si la escuela escanea los
documentos firmados, hay que respaldar ese directorio por separado. Está
apuntado en [08-RESPALDOS.md](08-RESPALDOS.md).

### Los archivos de la interfaz no se respaldan

Se reconstruyen con `npm run build`. Es lo correcto, pero significa que
restaurar en un servidor sin Node significa tener Node para reconstruir.

---

## Decisiones que requieren respuesta humana

Estas no son decisiones técnicas. Son de la escuela, y el código las deja
abiertas a propósito.

| Decisión | Variable | Qué pasa mientras tanto |
|---|---|---|
| ¿La ausencia consume la clase del plan? | `ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN` | No se consume nada y la ausencia queda marcada como pendiente |
| ¿Cuánto vive una clase recuperada? | `ESCUELA_MUSICA_DIAS_RECUPERACION` | No caduca nunca, y la deuda se muestra a la vista |
| ¿Con cuántos días se avisa de morosidad? | `ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD` | No hay aviso, pero la lista completa se ve |
| ¿Cuánto vale cada plan? | `planes.precio` | No se generan cobros con monto |
| ¿Cuántas clases incluye cada plan? | `planes.clases_incluidas` | No se descuenta nada del cupo |
| ¿Por cuánto sigue vigente la autorización de un menor? | `alumno_responsables.autorizacion_vence_el` | Queda en `NULL` |

La última es la más delicada: es una obligación legal con su responsable legal,
no una decisión de configuración. Ver
[07-SEGURIDAD-Y-LEY-19628.md](07-SEGURIDAD-Y-LEY-19628.md).

---

## Lo que hay que verificar, en orden

Esto es lo que habría que hacer, en este orden, antes de decir que la
aplicación funciona.

### 1. Compilar

```bash
cd api && composer install
cd web && npm install && npm run build
```

Lo primero que fallaría: cualquier error de sintaxis de PHP o de TypeScript. Es
lo más probable y lo más fácil de arreglar.

### 2. Las migraciones

```bash
cd api && php artisan migrate --force
```

Lo que hay que mirar: si el orden de las claves foráneas es correcto, si la
migración de Row Level Security corre sin errores y si `verificarColumnaTenant()`
detecta lo que debe detectar.

### 3. El aislamiento

```bash
php artisan escuela-musica:verificar-aislamiento
```

Si dice que el aislamiento es correcto, la Row Level Security está activa y el
rol de la conexión no la está saltando.

Si falla, **no seguir**: una escuela vería los datos de todas las demás.

### 4. Las pruebas

```bash
php artisan test --testsuite=Aislamiento
php artisan test --testsuite=Planes
php artisan test --testsuite=Agenda
php artisan test --testsuite=Recuperacion
```

Empezar por `Aislamiento`, que es la que más puede doler si el patrón de
contexto está mal.

### 5. Las imágenes

```bash
cd deploy
docker compose -f docker-compose.dev.yml build
```

### 6. Los respaldos, sobre una base descartable

```bash
./respaldos/respaldo.sh
./respaldos/restauracion.sh /var/backups/escuela-musica/respaldo-XXXX
```

Un respaldo que nunca se restauró es una suposición, no un respaldo.

### 7. Los puertos

```bash
docker compose -f docker-compose.prod.yml ps
```

Solo el proxy debe aparecer con un puerto publicado.

---

## Riesgos conocidos

| Riesgo | Gravedad | Mitigación |
|---|---|---|
| Las pruebas no corren | Alta | Son la primera verificación |
| El alcance de sesión en `TestCase` puede no funcionar | Media | Está anotado en el archivo; si falla, usar transacciones explícitas |
| Dos usuarios crean la misma clase a la vez | Media | La restricción de exclusión de PostgreSQL |
| La documentación de un menor vence sin fecha | Alta | Depende del cliente decidirlo |
| Los documentos escaneados no se respaldan | Alta | Respaldar el directorio aparte |
| El `entrypoint` aplica migraciones al arrancar | Media | Es lo estándar para despliegues de una sola aplicación, pero conviene revisar el código antes de reiniciar |

---

## Lo que sí se verificó, y hasta dónde

| Verificación | Resultado |
|---|---|
| Sintaxis de los tres guiones de shell (`bash -n`) | Correcta |
| Validador de aplicaciones del repositorio | Sin errores |
| Acentuación y ortografía en la documentación | Sin faltantes |
| Barrido de caracteres no españoles | Limpio |
| Que cada modelo apunte a columnas que las migraciones crean | Revisado a mano, no por un programa |
| Compilación de PHP | **No verificada** |
| Compilación de TypeScript | **No verificada** |
| Migraciones sobre PostgreSQL | **No verificadas** |
| Comportamiento en ejecución | **No verificado** |

La última línea es la que importa: **el comportamiento en ejecución no está
verificado.**

---

## Los dos roles de PostgreSQL: escritos, no ejecutados

La separación entre el rol de arranque (`escuela_musica_admin`) y el rol de la
aplicación (`escuela_musica_app`) está escrita en el `compose`, en
`deploy/postgres/inicializar-rol.sh` y en
[03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md). **No se ha ejecutado
ninguna vez.**

En el equipo donde se escribió este cambio no había PostgreSQL ni Docker. Queda
pendiente:

1. Levantar el `compose` con el volumen de PostgreSQL vacío y confirmar que el
   rol `escuela_musica_app` se crea sin error.
2. Confirmar con `SELECT rolname, rolsuper, rolbypassrls FROM pg_roles` que el
   rol de la conexión no es superusuario ni salta la RLS.
3. Correr `php artisan escuela-musica:verificar-aislamiento` y confirmar que
   sale con código 0.
4. Correrlo con `--detalle` para ver la tabla por tabla.
5. Probar el escenario que motiva todo el cambio: una escuela intentando leer
   matrículas de otra.

Lo único verificado es estático: `bash -n` pasa sobre el script y el `compose`
es YAML válido, ambas cosas comprobadas por `tools/validar_apps.py`. El
razonamiento se apoya en la documentación oficial de PostgreSQL 18, no en una
ejecución.