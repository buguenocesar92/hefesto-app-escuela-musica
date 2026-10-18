# 05. Configuración

## Lo que este sistema NO configura solo

Tres decisiones son de la escuela y no se pueden deducir de nada:

1. Si una ausencia consume la clase del plan.
2. Cuántos días vive una clase recuperada.
3. Con cuántos días se avisa de que un plan está por vencer.

**Las tres vienen vacías y no tienen valor por omisión en el código.**

Es tentador poner un valor que parezca sensato: "sí, consume", "90 días",
"7 días de aviso". El problema es que esos números no son neutros:

- "La ausencia consume" versus "no consume" cambia el ingreso de la escuela y la
  relación con la familia.
- "90 días" caduca una deuda que la escuela le debe a un alumno.
- "7 días de aviso" afirma un acuerdo sobre el cobro que nadie acordó.

En cada caso, el software estaría decidiendo por la escuela algo que es suyo, y
lo estaría decidiendo con seguridad, que es lo peor.

### Qué hace el sistema mientras tanto

| Regla sin configurar | Comportamiento |
|---|---|
| Consumo por ausencia | Se registra la asistencia. **No se consume nada.** La ausencia queda marcada como pendiente de decisión y aparece en el tablero |
| Caducidad de recuperación | La recuperación **no caduca**. La deuda sin fecha se muestra aparte |
| Aviso de morosidad | No hay aviso. La lista completa de morosos se sigue viendo |

La aplicación funciona igual con las tres vacías. Lo que cambia es que hay
trabajo humano pendiente, y la aplicación lo muestra en vez de esconderlo.

---

## Las tres reglas, en detalle

### 1. Consumo por ausencia

```bash
ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN=si     # la ausencia descuenta la clase
ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN=no     # la ausencia NO descuenta
ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN=       # sin definir
```

Se acepta `si`, `sí`, `no`, `true`, `false`, `1`, `0`. Cualquier otro valor se
trata como **sin definir**, no como un "sí".

Cómo lo resuelve `App\Services\Planes\ConsumidorDeClases`:

```
Alumno con plan, con cupo definido, asiste
    -> consume una clase

Alumno con plan, con cupo definido, falta
    -> la regla decide; si está sin definir, NO consume y queda pendiente

Alumno SIN plan (paga clase por clase)
    -> no hay cupo que tocar, y no se genera movimiento

Plan sin clases_incluidas definidas
    -> NO se descuenta. No se puede descontar de un cupo que nadie definió
```

**El valor se congela en la fila.** Si la escuela cambia la regla en marzo, las
asistencias de enero siguen diciendo qué pasó con ellas. Hay una prueba para
esto: `test_cambiar_la_regla_no_reescribe_las_asistencias_viejas`.

### 2. Caducidad de la clase recuperada

```bash
ESCUELA_MUSICA_DIAS_RECUPERACION=30    # caduca a los 30 días
ESCUELA_MUSICA_DIAS_RECUPERACION=      # no caduca
```

El plazo se cuenta en días completos desde la clase perdida, hasta el final de
ese día. Una recuperación creada a las 18:30 no vence antes que una creada a las
9:00 del mismo día.

Un valor de `0` o negativo se trata como **no configurado**: un plazo de cero no
significa "caduca ya", significa que está mal puesto.

Para revisarlas a mano:

```bash
php artisan escuela-musica:vencer-recuperaciones
php artisan escuela-musica:vencer-recuperaciones --aplicar
```

Sin la regla configurada, el comando **no vence nada** y lista la deuda sin
fecha, una por una.

### 3. Aviso de morosidad

```bash
ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD=7   # avisa 7 días antes
ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD=    # no avisa
```

Solo cambia la etiqueta del listado. La lista completa se muestra igual.

---

## Los precios

No hay ningún precio por omisión, en ningún lado.

`planes.precio` admite `NULL`. Con el precio en `NULL`:

- el plan aparece en la lista como "sin definir";
- no se genera ningún cobro con monto;
- el estado de cuenta muestra "sin monto definido" y **no lo suma al saldo**.

`pago.monto` admite `NULL` por la misma razón, aunque al **anotar un cobro a
mano** el formulario sí lo exige: si alguien cobra, tiene que decir cuánto
cobró.

`AlumnoFactory` y `PlanFactory` sacan `monto` y `precio` en `NULL`. Para probar
el camino con valores hay estados explícitos, `->conPrecio(1)` y `->conMonto(1)`,
con valores que **no son de la vida real**.

---

## Variables de entorno

### Aplicación

| Variable | Por omisión | Para qué |
|---|---|---|
| `APP_ENV` | `local` | `local` o `production` |
| `APP_KEY` | vacío | Cifra la sesión. **Sin ella no hay ingreso** |
| `APP_DEBUG` | `false` | En producción, `false` siempre |
| `APP_TIMEZONE` | `America/Santiago` | La agenda guarda marcas con zona |
| `APP_URL` | vacío | Debe ser el dominio real en producción |

### Base de datos

| Variable | Por omisión | Nota |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | **No cambiar.** La RLS no existe en otro motor |
| `DB_HOST` | `postgres` | |
| `DB_DATABASE` | `escuela_musica` | |
| `DB_USERNAME` | `escuela_musica_app` | **No puede ser `postgres`**: ver abajo |
| `DB_PASSWORD` | vacío | Sin ella no hay conexión |

### El rol de la base no puede ser superusuario

Con superusuario o con `BYPASSRLS`, PostgreSQL **ignora todas las políticas** y
una escuela vería los datos de todas las demás.

```bash
php artisan escuela-musica:verificar-aislamiento
```

Comprueba eso. En Docker, el rol que crea el contenedor es el que queda, así que
`POSTGRES_USER` debe ser un rol propio y no `postgres`.

### Sesión

| Variable | Por omisión | Nota |
|---|---|---|
| `SESSION_DRIVER` | `file` | |
| `SESSION_LIFETIME` | `480` | Minutos |
| `SESSION_SECURE_COOKIE` | `true` | En producción debe quedar en `true` |
| `SESSION_SAME_SITE` | `lax` | |

La cookie de sesión no se persiste: se pierde al cerrar el navegador. Para una
aplicación interna de una PyME es lo razonable y evita dejar acceso permanente a
datos de menores en un equipo compartido.

### Identidad

| Variable | Para qué |
|---|---|
| `AUTHENTIK_BASE_URL` | Dirección del proveedor |
| `AUTHENTIK_ISSUER` | Debe coincidir con el claim `iss` del token |
| `AUTHENTIK_CLIENT_ID` | |
| `AUTHENTIK_CLIENT_SECRET` | |
| `AUTHENTIK_REDIRECT_URI` | **Debe coincidir exactamente** con la ruta `/api/v1/auth/callback` y con lo registrado en Authentik |
| `AUTHENTIK_VERIFICAR_FIRMA` | En producción, `true` siempre |

`AUTHENTIK_VERIFICAR_FIRMA=false` se puede poner en desarrollo. En producción
significa que cualquiera que alcance el callback podría presentar un token
hecho a mano e identificarse como quien quiera.

### Otras

| Variable | Por omisión | Para qué |
|---|---|---|
| `ESCUELA_MUSICA_ROLES_QUE_VEN_PAGOS` | `direccion,administracion` | Lista explícita. Vacía significa que nadie ve pagos |
| `ESCUELA_MUSICA_EXIGIR_AUTORIZACION` | `true` | Obliga a tener autorización registrada para tratar datos de menores |
| `ESCUELA_MUSICA_RETENCION_BITACORA_HORAS` | `8760` | Un año. Ver la nota de abajo |
| `ESCUELA_MUSICA_POR_PAGINA_MAXIMO` | `100` | Techo de filas por consulta |

### Sobre la retención de la bitácora

`8760` es el valor por omisión, y es el mismo que usan las demás aplicaciones de
Hefesto. **No es un plazo legal ni una decisión del cliente**: es un valor
generoso para que el recorte no se ejecute el primer día y borre medio año de
historial de un tirón.

La retención real depende de la política de tratamiento de datos de la escuela.
Ver [07-SEGURIDAD-Y-LEY-19628.md](07-SEGURIDAD-Y-LEY-19628.md).

---

## Dónde vive cada cosa

| Configuración | Dónde |
|---|---|
| Variables de la API | `api/.env` |
| Variables de la interfaz | `web/.env` |
| Valores con valor por omisión | `api/config/escuela-musica.php` |
| Identidad | `api/config/authentik.php` |
| Conexión | `api/config/database.php` |
| Composición | `deploy/docker-compose.prod.yml` |

`config/escuela-musica.php` es donde se leen las tres reglas. Ahí se puede ver
de un vistazo que las tres devuelven `null` cuando no están configuradas.

---

## Comprobar que la configuración quedó como se quiso

```bash
# El aislamiento
docker compose -f docker-compose.prod.yml run --rm api \
    php artisan escuela-musica:verificar-aislamiento

# La morosidad y las ausencias por definir
docker compose -f docker-compose.prod.yml run --rm api \
    php artisan escuela-musica:revisar-morosidad

# La deuda de clases
docker compose -f docker-compose.prod.yml run --rm api \
    php artisan escuela-musica:vencer-recuperaciones
```

**Ninguno de estos comandos se ha ejecutado.** Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).