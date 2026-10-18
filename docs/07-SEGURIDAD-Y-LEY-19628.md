# 07. Seguridad y Ley 19.628

Este documento trata los datos personales de los alumnos. La mayoría de ellos
son de **menores de edad**, y eso cambia la forma en que hay que mirarlos.

---

## 1. Qué datos se guardan

En `alumnos`:

| Columna | Qué es |
|---|---|
| `nombre`, `apellido` | Nombre completo del menor |
| `rut` | Identificador único chileno |
| `fecha_nacimiento` | Permite deducir la edad |
| `direccion` | La casa donde vive el menor |
| `telefono` | Contacto de la familia |
| `correo` | Del adulto responsable, cuando el alumno es menor |

En `responsables`: nombre, RUT, teléfono, correo y dirección del adulto.

**Todos son datos personales.** Para un menor no hay una categoría menos
sensible: el nombre completo, el RUT y la dirección de un niño dicen dónde vive
y quién es.

---

## 2. La autorización del adulto responsable

### Por qué es un requisito y no un detalle

Un menor no puede ejercer por sí mismo el derecho de acceso, de rectificación o
de oposición sobre sus datos. Quien lo hace es el adulto responsable, y sin esa
autorización la escuela no debería tratar los datos del menor.

Por eso la autorización **no es una nota** en la ficha del alumno: es una
columna en `alumno_responsables`, con si está registrada, cuándo se registró y
qué documento la respalda.

### Qué hace el sistema

| Situación | Qué pasa |
|---|---|
| Alumno menor sin responsable con autorización | La ficha muestra un aviso en rojo. Con `ESCUELA_MUSICA_EXIGIR_AUTORIZACION=true`, no se permite dar de alta clases |
| `ESCUELA_MUSICA_EXIGIR_AUTORIZACION=false` | Se permite operar sin autorización registrada. Es una decisión consciente de la escuela |
| La fecha de vencimiento de la autorización | Queda en `NULL`. Ver la sección siguiente |

El valor por omisión es `true` porque se trata de datos de un menor: es la
postura segura. Bajarlo a `false` es algo que la escuela puede hacer a
conciencia, y queda en el registro.

---

## 3. Lo que NO se puso: plazos de retención

### El campo queda en `NULL`, a propósito

`alumno_responsables.autorizacion_vence_el` admite `NULL`, y `NULL` significa
**"la escuela todavía no lo ha definido"**.

No se le puso un plazo.

Es muy tentación poner "un año": es el número que aparece en la mayoría de los
formularios de consentimiento y en muchas instalaciones. Ponerlo aquí sería
afirmar algo falso sobre una obligación legal, con consecuencias reales: cuando
la autorización "caducara", el sistema dejaría de tratar los datos del menor, y
la familia podría interpretar que la escuela dejó de tener permiso para
impartirle clases, cuando en realidad lo que caducó fue una fecha que nadie
definió.

La retención y la vigencia de una autorización dependen de:

- la política de tratamiento de datos de la escuela;
- lo que el adulto responsable haya aceptado por escrito;
- la expresión de voluntad del menor cuando sea posible;
- y de lo que el responsable legal de la escuela decida con su asesoría.

Ninguna de esas cuatro cosas la puede decidir el software.

**Este es un punto pendiente de resolver con el cliente.** Está anotado en
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md) como decisión que requiere
respuesta humana, no técnica.

### Qué se puede hacer mientras tanto

La escuela puede dejar el campo en `NULL` y llevarlo por escrito. Cuando defina
el plazo, se configura y el sistema lo empieza a mostrar.

---

## 4. Qué ve cada rol

La autorización no sirve de nada si todo el mundo ve todo.

| Dato | direccion | administración | docente | recepción |
|---|---|---|---|---|
| Nombre y apellido del alumno | Sí | Sí | Sí | Sí |
| RUT | Sí | Sí | **No** | Sí |
| Fecha de nacimiento | Sí | Sí | **No** | Sí |
| Dirección y teléfono | Sí | Sí | **No** | Sí |
| Datos del responsable | Sí | Sí | **No** | Sí |
| Pagos | Sí | Sí | **No** | Solo si se autoriza |
| Autorización registrada | Sí | Sí | Sí | Sí |

La columna "Sí" de autorización registrada para el docente es a propósito: si el
alumno es menor y no tiene autorización, el docente tiene que verlo. Es
información de seguridad, no de contacto.

### Dónde está implementado

- La fila de la lista de alumnos **no trae** dirección ni teléfono. Se necesitan
  para llamar a una clase, no para saber dónde vive el menor.
- `AlumnoController::show()` mete los datos personales en el bloque
  `datos_personales` **solo** si la persona tiene permiso, y si no, manda
  `null` en vez de mandarlos.
- `PagoPolicy` usa la lista explícita de `ESCUELA_MUSICA_ROLES_QUE_VEN_PAGOS`.

---

## 5. El aislamiento entre escuelas

Un doble error como este sería que una escuela viera los alumnos de otra. Por eso
todas las tablas llevan `tenant_id` y Row Level Security con `WITH CHECK`.

Y cuando una política rechaza, la respuesta es **404, no 403**: un 403 confirmaría
que el identificador existe en alguna parte, y con eso ya se está entregando
información sobre otra escuela sin ver un solo dato.

Ver [03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md).

---

## 6. La bitácora de accesos

`bitacora_accesos` registra quién entró, a qué hora, desde qué dirección y con
qué resultado.

Es lo que permite responder "¿quién consultó los pagos de esta familia?".

### Qué NO queda en la bitácora

| No se registra | Por qué |
|---|---|
| Los intentos de ingreso fallidos | Ocurren **antes** de que exista contexto de escuela, y la tabla tiene RLS. El intento va al archivo de registro de la aplicación, que no tiene datos de personas |
| El cuerpo de las peticiones | Un cuerpo puede contener datos de un menor |
| Los tokens de acceso | No se guardan |

### Su retención

`ESCUELA_MUSICA_RETENCION_BITACORA_HORAS`, por omisión `8760` (un año), con un
recorte diario a las 03:00.

Ese valor **no es un plazo legal**: es el mismo que usan las demás aplicaciones
de Hefesto, elegido para que el recorte no se ejecute el primer día y borre medio
año de historial de un tirón. La retención real la decide la escuela.

---

## 7. El ingreso

No hay contraseñas en esta aplicación.

El ingreso es OIDC contra Authentik, y la aplicación guarda solo dos cosas del
proveedor: el identificador estable del usuario, para reencontrar la cuenta, y
el correo.

### No se crea ninguna cuenta al volar

Si el correo del proveedor no está dado de alta, el ingreso se rechaza.

No se crea una cuenta a partir de un correo que llegó por internet: eso
permitiría que cualquiera que controlara un buzón entrara con el rol que le
tocara. El alta de cuentas es una tarea de la dirección de la escuela.

### La firma del token

Se verifica contra las llaves del proveedor, y además se revisan el emisor y la
audiencia. Verificar la firma sin mirar el emisor confirma que el token lo firmó
la llave correcta, que no es lo mismo que confirmar que el token era para esta
aplicación.

`AUTHENTIK_VERIFICAR_FIRMA` se puede apagar en desarrollo. En producción, no.

---

## 8. El transporte

| Capa | Qué hace |
|---|---|
| Proxy (Caddy) | Termina TLS y renueva el certificado |
| Cabeceras | `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy` |
| Cookie | `HttpOnly`, `Secure` en producción, `SameSite=Lax` |
| Interfaz | `noindex, nofollow, noarchive`: es una aplicación interna |

La cookie no se expone a JavaScript (`HttpOnly`) y la interfaz nunca habla
directamente con la API: siempre pasa por el proxy del servidor, para que la
cookie no viaje a un segundo origen.

---

## 9. Lo que falta y hay que decidir con el cliente

| Punto | Por qué no lo decidió el software |
|---|---|
| Plazo de vigencia de la autorización | Es una obligación legal con su responsable legal, no una decisión técnica |
| Plazo de retención de los datos de un menor tras que deja la escuela | Igual que el anterior |
| Si se puede exportar o eliminar el expediente de un menor al salir | La ley da derechos, y el procedimiento es del cliente |
| Si hay que notificar a las familias de una brecha de seguridad | Depende del procedimiento y del alcance legal del hecho |

Ninguno de estos cuatro tiene un valor por omisión en el código.