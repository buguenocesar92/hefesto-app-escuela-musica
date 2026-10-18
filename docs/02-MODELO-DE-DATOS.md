# 02. Modelo de datos

## Las diecisiete tablas de negocio

Todas llevan `tenant_id` y todas están protegidas por Row Level Security, salvo
las tres que se explican al final de este documento.

| Tabla | Qué guarda |
|---|---|
| `escuelas` | La escuela. Es el tenant. **Sin RLS.** |
| `docentes` | Quien da clases |
| `usuarios` | Quien entra al sistema. Sin contraseñas |
| `salas` | Los espacios físicos |
| `alumnos` | Los alumnos, con sus datos personales |
| `responsables` | Los adultos responsables |
| `alumno_responsables` | El vínculo y la autorización |
| `planes` | Los paquetes mensuales |
| `inscripciones` | Un alumno suscrito a un plan |
| `pagos` | Cuotas y clases sueltas |
| `clases` | Los eventos concretos de la agenda |
| `inscripciones_clase` | Quién va a qué clase |
| `asistencias` | Presente o ausente |
| `recuperaciones` | La deuda por faltar |
| `movimientos_clase_plan` | El libro de consumo de clases |
| `instrumentos` | El inventario |
| `prestamos_instrumento` | Qué tiene cada alumno |
| `bitacora_accesos` | Quién entró y cuándo |

---

## Las decisiones que importan

### Una clase es un evento, no un curso

"Piano los martes a las 18:00" es un curso. La fila de `clases` es la clase del
martes 3, de 18:00 a 19:00, con un docente y en una sala.

La asistencia y el consumo de clases se anotan contra la fila, porque contra el
curso no se puede saber si la persona fue el 3 o el 10.

Por eso `clases` tiene `inicio` y `termina`, y no un `dia_semana`.

### El libro de clases es un libro, no un contador

Un contador en `planes` respondería "¿cuántas le quedan?", pero no dejaría rastro
de **por qué** bajó. Con `movimientos_clase_plan` se puede reconstruir el saldo
clase por clase: el consumo de hoy, la devolución de una recuperación vencida, el
ajuste que hizo la administración.

Un `ajuste` sin motivo se rechaza **en la base**, con una restricción `CHECK`, no
solo en la interfaz. Un ajuste sin explicación no sirve para nada.

### Cuotas y clases sueltas en la misma tabla

`pagos` tiene `concepto`: `cuota_plan` o `clase_suelta`.

El estado de cuenta que mira la escuela es de la persona, no del tipo de cargo:
"¿me debe algo?". Si estuvieran en tablas distintas, cada pantalla de estado de
cuenta tendría que consultar las dos y sumar en código, y en el momento en que
alguien olvide sumar una, el saldo mostrado queda mal.

### `pagos` no tiene columna `estado`

El estado se deriva de si está pagado y si está anulado. Derivable significa que
no puede contradecirse: no hay forma de que las tres cosas sean incompatibles.

---

## Las columnas que quedaron en NULL a propósito

Esta es la parte del modelo que más conviene leer antes de tocar nada.

### `planes.clases_incluidas`, `planes.precio`, `planes.vigencia_dias`

Admiten `NULL`. `NULL` no es cero: es "la escuela todavía no lo ha definido".

| Si está en NULL | Qué hace el sistema |
|---|---|
| `clases_incluidas` | **No descuenta clases.** No hay cupo que tocar |
| `precio` | No genera cobro con monto |
| `vigencia_dias` | No calcula el fin del período |

Que no reste es preferible a restar una cantidad que nadie definió. Cero
significaría "no incluye nada", que es una afirmación que el software no puede
hacer.

### `asistencias.consume_clase_plan`

Es un tri-estado, y es la columna más importante del modelo.

| Valor | Significado |
|---|---|
| `true` | Esta ausencia descontó una clase del plan |
| `false` | Esta ausencia **no** descontó nada |
| `NULL` | La regla no está configurada, el sistema no tocó el cupo |

El valor se **congela** en la fila. Si más adelante la escuela cambia la regla,
las asistencias viejas siguen diciendo qué pasó con ellas y no se reescriben. Hay
una prueba para esto.

### `recuperaciones.vence_el`

`NULL` = la recuperación **no caduca**.

Es tentador poner 90 días, que es el número que aparece en la mayoría de los
reglamentos internos. No se hizo: es un número inventado con consecuencias
reales, porque caduca una deuda que la escuela le debe a una familia.

Con el plazo configurado, la fecha sale de
`ESCUELA_MUSICA_DIAS_RECUPERACION`. Sin él, no hay fecha.

### `alumno_responsables.autorizacion_vence_el`

`NULL` = la escuela todavía no definió por cuánto tiempo sigue vigente la
autorización. Ver [07-SEGURIDAD-Y-LEY-19628.md](07-SEGURIDAD-Y-LEY-19628.md).

### `pagos.monto`

`NULL` = el plan todavía no tiene precio. El estado de cuenta lo muestra como
"sin monto definido" y **no lo suma al saldo**, porque sumar cero afirmación
falsa.

---

## Deuda de clases: el saldo que se divide en dos

`SaldoDeRecuperaciones::saldoDe()` no devuelve un total: devuelve tres números.

```
pendientes       todas las que se pueden recuperar todavía
  sin_caducidad    las que NO tienen fecha de vencimiento
  con_vencimiento  las que sí la tienen
```

Con un total único, la deuda sin fecha quedaría escondida dentro de una cifra
que parece controlada. La división es lo que la hace visible.

---

## La relación con la base de datos

### Las tres tablas sin Row Level Security

| Tabla | Por qué |
|---|---|
| `escuelas` | Se consulta en el ingreso, cuando no hay contexto. Compensada con la vista `vw_escuelas_publicas` |
| `sessions` | La resuelve Laravel por identificador. Ponerle RLS rompería el ingreso |
| `migrations` | Es infraestructura, no datos de nadie |

### La integridad entre tablas la cubre la RLS

Como **toda** tabla de negocio tiene su propia política, un `JOIN` entre dos
tablas de la misma escuela filtra las dos. Un alumno no puede quedar apuntando a
un responsable de otra escuela: la fila de ese responsable queda filtrada y la
relación aparece vacía, no corrupta.

Eso significa que la integridad entre tablas **no** hay que vigilarla a mano en
las consultas: viene dada. Lo que sí decide la RLS es únicamente de quién son
las filas, no si la persona tiene permiso para verlas. De eso se encarga la
política.

Lo que uno u otro capa no puede hacer sola:

- Solo RLS: un docente vería la dirección de la casa de todos los menores.
- Solo la política: un docente podría abrir por URL la ficha de un alumno de
  otra escuela.

Por eso existen las dos capas. Ver
[03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md).

---

## Diagrama de las relaciones centrales

```
   escuelas
      |
      +--- docentes --------+
      |                      |
      +--- usuarios          |
      |         |            |
      |         v            v
      +--- salas          clases  <---- agendadas con inicio y termina
                              |
            +-----------------+------------------+
            |                 |                  |
            v                 v                  v
   alumnos eau        asistencias        recuperaciones
      |     |              |                  |
      |     +--------------                  |
      |                                        |
      v                                        v
inscripciones --> planes          movimientos_clase_plan
      |
      v
    pagos
```

Un alumno puede estar en varias inscripciones a lo largo del tiempo, y cada una
apunta al plan que tenía en ese momento. Los pagos apuntan a la inscripción, no
al plan, para que "la cuota de octubre" siga siendo la cuota de octubre aunque
el plan haya cambiado.

---

## Lo que no tiene tabla

| Dato | Por qué no tiene tabla |
|---|---|
| Tipo de instrumento | Texto libre. Nadie filtra por "guitarra" para poder prestárselo a alguien |
| Método de pago | Texto libre. Efectivo, transferencia, tarjeta: la escuela conoce más opciones que las que podemos listar |
| Parentesco | Texto libre. Las familias reales no caben en una lista |

Cuando un dato admite valores libres y nadie filtra por él, una tabla es solo
una restricción más que mantener.