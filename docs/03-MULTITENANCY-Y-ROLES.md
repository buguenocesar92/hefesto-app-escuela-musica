# 03. Multitenant y roles

Este sistema sirve a **una sola escuela a la vez** por instalación, pero está
escrito como multiescuela: las tablas están separadas por `tenant_id` y
protegidas con Row Level Security.

Se usa el patrón ya establecido en `apps/colegio` y en
`apps/portal-autoservicio`. **No se inventó un cuarto enfoque.**

---

## 1. El mecanismo

### La variable de contexto

La RLS lee el identificador de la escuela desde la variable de sesión de
PostgreSQL `app.tenant_id`.

La escribe **una sola clase**: `App\Services\Tenancy\ContextoEscuela`. Si
alguien la fija por otro lado, el comportamiento deja de estar garantizado.

### Alcance de TRANSACCIÓN, no de sesión

El código usa `set_config(..., true)`, que es alcance de **transacción**:

```php
DB::select('SELECT set_config(?, ?, true)', ['app.tenant_id', $tenantId]);
```

La diferencia no es un detalle. Con un `SET` normal, el valor **sobrevive a la
petición**. El worker de colas reutiliza la misma conexión para trabajos de
escuelas distintas, así que un trabajo de la escuela A podría leer datos de la
escuela B. Ese es exactamente el fallo que este diseño evita.

Por eso `establecer()` **exige** estar dentro de una transacción y lanza una
excepción si no lo está.

### La función falla cerrada

```sql
CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
...
    IF valor IS NULL OR valor = '' THEN
        RAISE EXCEPTION USING ERRCODE = 'insufficient_privilege', ...
    END IF;
```

Cuando no hay contexto, **lanza excepción**. No devuelve `NULL` ni cadena
vacía.

Eso parece poco útil y es lo más importante del diseño: si devolviera `NULL`,
un día alguien "arreglaría" el código para tolerarlo y el sistema empezaría a
devolver cero filas en vez de un error. Con cero filas, la consulta parece
funcionar mientras la persona lee datos de la escuela equivocada. Un error
ruidoso es preferible a un silencio cómodo.

### FORCE ROW LEVEL SECURITY

```sql
ALTER TABLE public.alumnos ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.alumnos FORCE ROW LEVEL SECURITY;
```

Sin `FORCE`, la política se aplica a los demás roles pero **no al dueño de la
tabla**. En desarrollo el dueño suele ser el mismo rol que corre la aplicación,
así que sin `FORCE` el aislamiento sería decorativo justo en el entorno donde se
prueba.

### La política

```sql
CREATE POLICY politica_aislamiento_alumnos ON public.alumnos
    FOR ALL
    TO PUBLIC
    USING (tenant_id = public.app_tenant_id())
    WITH CHECK (tenant_id = public.app_tenant_id());
```

`USING` filtra lo que se lee. `WITHOUT CHECK` filtra lo que se escribe: sin
`WITH CHECK`, alguien podría insertar una fila con el `tenant_id` de otra
escuela.

### La migración verifica lo que activa

`2026_10_18_000019_activar_row_level_security.php` recorre la lista de tablas
protegidas y **falla con excepción** si alguna no tiene columna `tenant_id`.

Sin esa comprobación, un error de tipeo en el nombre dejaría una tabla de datos
de negocio sin política y la migración pasaría igual, en silencio. Con datos de
menores y de pagos, un silencio así es inaceptable.

Las diecisiete tablas protegidas:

```
docentes              usuarios            salas
alumnos               responsables        alumno_responsables
planes                inscripciones       pagos
clases                inscripciones_clase asistencias
recuperaciones        movimientos_clase_plan
instrumentos          prestamos_instrumento
bitacora_accesos
```

---

## 2. El contexto en cada petición

`App\Http\Middleware\ResolverTenant` va en el grupo `web`, **antes** que
`auth`.

```
peticion
  -> ResolverTenant   fija app.tenant_id en una transaccion
     -> auth          verifica la sesion
        -> controlador  -> consulta con la RLS activa
```

Va antes que `auth` porque la política necesita el contexto para devolver
filas. Y por eso no puede usar `$request->user()`: en ese punto todavía no hay
usuario resuelto. Usa `auth()->guard('web')->user()`, que sí lee la sesión.

Las peticiones sin sesión (ingreso, callback, salud) pasan sin transacción. Si
esas rutas tocaran una tabla protegida, `app_tenant_id()` lanzaría excepción y
la petición fallaría: **el aislamiento falla cerrado, nunca abierto**.

---

## 3. La tabla de escuelas NO tiene RLS

`escuelas` se consulta durante el acceso, cuando todavía no hay sesión y por lo
tanto no hay `app.tenant_id` que comparar. Es una excepción consciente.

La compensación es doble:

1. El rol de la aplicación **no es superusuario** ni tiene `BYPASSRLS`. Con
   cualquiera de los dos, PostgreSQL ignora las políticas por completo. El
   comando verificador lo comprueba.
2. Existe la vista `vw_escuelas_publicas`, que expone **tres columnas**.

### La vista, y que se use de verdad

```sql
CREATE VIEW public.vw_escuelas_publicas AS
    SELECT slug, nombre, activo
    FROM public.escuelas
    WHERE activo = true;
```

No expone `razon_social` ni `rut`: no hacen falta para elegir dónde entrar y son
los datos que nadie debería poder enumerar desde un formulario abierto.

**Y se consulta de verdad**, en `AuthController::escuelas()`, que es lo que llena
el selector de la pantalla de entrada.

Eso no es un detalle. En otras aplicaciones de Hefesto la vista se creó y nunca
se consultó, mientras la documentación afirmaba que sí: se prometía una
protección que no existía. Aquí hay dos cosas que lo impiden:

- `AuthController::escuelas()` lee la vista, y si se borrara, **el ingreso
  dejaría de funcionar**.
- La prueba `test_el_selector_de_ingreso_usa_la_vista_publica` falla si el
  selector deja de leerla, y comprueba además que la respuesta no incluya
  columnas que la tabla completa sí tiene.

---

## 4. Las otras dos tablas sin RLS

| Tabla | Por qué |
|---|---|
| `sessions` | La resuelve Laravel por identificador de sesión, no la aplicación. Además, la sesión se escribe **antes** de que exista contexto de escuela, que es justo cuando hace falta crear la fila. Ponerle RLS rompería el ingreso |
| `migrations` | Es infraestructura, no datos de nadie |

---

## 5. Roles

Los roles son texto en `usuarios.rol`. Cuatro:

| Rol | Qué puede hacer |
|---|---|
| `direccion` | Todo: administra, ve pagos, registra asistencia |
| `administracion` | Todo lo de la dirección |
| `docente` | Ve la agenda y la lista de alumnos. Registra asistencia. **No ve pagos ni datos personales** |
| `recepcion` | Ve alumnos y registra asistencia. Ve pagos si se le autoriza |

### Lo que decide la RLS y lo que decide la política

| Capa | Qué decide |
|---|---|
| **RLS** | De qué escuela son las filas. No dice nada de permisos |
| **Política** | Si esta persona, con este rol, puede ver esta fila |

Con una sola de las dos hay agujeros:

- Solo RLS: un docente vería la dirección de todos los menores de su escuela.
- Solo la política: un docente podría abrir por URL la ficha de un alumno de
  otra escuela.

### La política más estricta: los pagos

Ver cuánto paga una familia no es algo que un docente necesite para impartir una
clase. Por eso la lista de roles que ven pagos es **explícita**:

```
ESCUELA_MUSICA_ROLES_QUE_VEN_PAGOS=direccion,administracion
```

No es "todos los que pueden entrar". Una lista vacía significa que nadie lo ve,
no que todos.

### 404, no 403

Cuando una política rechaza, la respuesta es **404**:

```php
$exceptions->render(function (AuthorizationException $e, $request) {
    if ($request->is('api/v1/*')) {
        return response()->json(['mensaje' => 'No encontrado.'], 404);
    }
});
```

Un 403 confirmaría que el identificador existe en alguna parte. Con 403, alguien
podría recorrer identificadores y aprender qué escuelas tienen cuántos alumnos,
sin ver un solo dato personal. Hay una prueba que lo comprueba:
`test_la_ficha_de_un_alumno_de_otra_escuela_responde_404_y_no_403`.

---

## 6. Cómo se comprueba

```
php artisan escuela-musica:verificar-aislamiento
php artisan escuela-musica:verificar-aislamiento --detalle
```

Comprueba:

1. Que el rol de la conexión **no** sea superusuario ni tenga `BYPASSRLS`.
2. Que cada tabla con `tenant_id` tenga RLS **activada y forzada**.
3. Que exista la política de aislamiento de cada tabla.
4. Que la tabla `escuelas` no tenga RLS, por diseño.
5. Que la vista pública exista y exponga exactamente `slug`, `nombre` y `activo`.
6. Que cada modelo con `PerteneceAEscuela` apunte a una columna que exista.

El mismo nombre y el mismo propósito que en las otras aplicaciones de Hefesto:
`colegio:verificar-aislamiento`, `gimnasio:verificar-aislamiento`,
`parqueadero:verificar-aislamiento`.

**Este comando no se ha ejecutado.** Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).

---

## 7. Los dos roles de PostgreSQL

La base de esta aplicación corre con **dos** roles, no con uno. La separación es
deliberada: es lo que hace que el aislamiento entre escuelas funcione en vez de
quedar como decoración.

### Qué es cada rol

| Rol | Nombre | De dónde sale | Para qué sirve | ¿Lo usa la aplicación? |
|---|---|---|---|---|
| **Arranque** | `escuela_musica_admin` | `POSTGRES_USER` | Crear la base `escuela_musica` y las tareas administrativas del despliegue | **Nunca** |
| **Aplicación** | `escuela_musica_app` | `DB_USERNAME`, creado por `deploy/postgres/inicializar-rol.sh` | Todo el tráfico de la API | **Sí, este y solo este** |

El rol de arranque no es una convención del estudio: la imagen oficial de
PostgreSQL ejecuta `initdb --username="$POSTGRES_USER"`, así que ese rol nace
**superusuario**. Por eso su contraseña va en `DB_ADMIN_PASSWORD`, separada de
`DB_PASSWORD`.

### Por qué con un solo rol no servía

La documentación de PostgreSQL 18 lo dice de forma textual:

> *«Superusers and roles with the `BYPASSRLS` attribute always bypass the row
> security system when accessing a table. Table owners normally bypass row
> security as well, though a table owner can choose to be subject to row
> security with `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.*

La segunda frase es la clave. `FORCE ROW LEVEL SECURITY` sujeta al **dueño** de
la tabla, pero **no al superusuario**. Mientras la aplicación conectara con el
rol de `POSTGRES_USER`, ninguna política de este documento se iba a aplicar: una
escuela habría visto las matrículas y los datos de contacto de las demás, sin
error y sin aviso. El aislamiento entre escuelas era decorativo.

### Los atributos van todos escritos

`deploy/postgres/inicializar-rol.sh`, montado en
`/docker-entrypoint-initdb.d/`, crea el rol con `NOSUPERUSER`, `NOCREATEDB`,
`NOCREATEROLE`, `NOINHERIT` y `NOBYPASSRLS` **explícitos todos**, aunque
`NOBYPASSRLS` ya sea el valor por omisión.

Se escriben igual a propósito: un rol verificado no debería depender de un valor
por omisión que una versión nueva puede cambiar. El script también falla
ruidosamente si falta `POSTGRES_DB`, `APP_DB_ROLE` o `APP_DB_PASSWORD`.

### Por qué el rol de aplicación es dueño de la base

Justo después de crearlo, el script corre:

```sql
ALTER DATABASE escuela_musica OWNER TO escuela_musica_app;
```

Las migraciones de Laravel se ejecutan con el rol de la aplicación y necesitan
**crear tablas**. La única forma de darles ese permiso sin convertirlos en
superusuario es que ese rol sea el dueño de la base. Desde PostgreSQL 15 el
esquema `public` pertenece al dueño de la base, así que con esto el rol ya
puede crear tablas ahí.

Y este es el punto exacto del diseño: al ser el dueño, **las tablas quedan a su
nombre**, y por eso el `FORCE ROW LEVEL SECURITY` que usan las tablas sí lo
sujeto. Ser dueño no es saltarse la política; es lo que hace que la política lo
alcance. Un superusuario la saltearía siempre.

### Cómo se comprueba

```
php artisan escuela-musica:verificar-aislamiento
php artisan escuela-musica:verificar-aislamiento --detalle
```

El prefijo del comando lleva un guion: `escuela-musica:`, no `escuela_musica:`.
La opción `--detalle` muestra cada tabla en lugar del resumen.

El comando consulta `pg_roles` sobre el rol de la conexión y falla con código 1
si ese rol es superusuario o tiene `BYPASSRLS`, y después revisa que cada tabla
con `tenant_id` tenga RLS activada y forzada, que exista su política, que la
tabla `escuelas` no tenga RLS por diseño, y que la vista pública exponga
exactamente `slug`, `nombre` y `activo`.

**Este comando no se ha ejecutado.** En el equipo donde se escribió este cambio
no había PostgreSQL ni Docker. El diseño es correcto y está sostenido en la
documentación oficial, pero nadie lo ha corrido. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).