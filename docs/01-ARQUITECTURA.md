# 01. Arquitectura

> PostgreSQL corre con dos roles: uno de arranque y otro de aplicación. Solo el
> de aplicación conecta con el sistema, y es el que las políticas de
> aislamiento pueden alcanzar. Ver [03](03-MULTITENANCY-Y-ROLES.md).

## Qué es y qué no es

Es una aplicación de gestión para una **escuela de música y arte** de tamaño
pequeño. No es un colegio.

No hay plan de estudios, ni notas, ni conducta escolar, ni asistencia por
curso con un promedio. Lo que hay es:

- alumnos que pagan un plan mensual o pagan clase por clase;
- clases con un docente y en una sala, en un horario;
- asistencia, con una regla de consumo por ausencia que la escuela decide;
- clases recuperadas, con una caducidad que la escuela también decide;
- pagos y estado de cuenta;
- instrumentos que se prestan y vuelven.

---

## Las tres piezas

```
                      +----------------------------+
   navegador  <----> |  proxy (Caddy)             |
                      |  publica 80 y 443          |
                      +----------------------------+
                                  |
                +-----------------+------------------+
                |                                    |
        +-------+--------+                   +-------+-------+
        |  interfaz      |                   |  API          |
        |  Nuxt 3        |  reenvía /api     |  Laravel 13   |
        |  puerto 3000   | ----------------> |  puerto 8080  |
        +----------------+                   +-------+-------+
                                                         |
                                                  +------+------+
                                                  |  PostgreSQL |
                                                  |  puerto 5432|
                                                  +-------------+
```

### Por qué el proxy va por delante

La sesión vive en una cookie que emite la API. Si el navegador la mandara
directo a la API, esa cookie viajaría a un origen distinto del de la interfaz.
Con el proxy, el navegador habla siempre con el mismo origen y es el servidor de
la interfaz el que habla con la API.

Consecuencia: **en producción, el proxy es el único servicio con puertos
publicados**. Ni 5432, ni 6379, ni 8080, ni 3000.

### Por qué la interfaz reenvía en lugar de llamar directo

`web/server/api/[...ruta].ts` existe por la misma razón: la cookie de sesión no
tiene que viajar a un segundo origen. La interfaz es el punto de entrada único
del navegador.

Además permite poner en la interfaz reglas que no deberían estar en el
navegador, como el origen que se le reenvía a la API.

### Por qué PostgreSQL y no otro motor

El aislamiento entre escuelas está implementado con **Row Level Security**, que
es una función de PostgreSQL. En SQLite o MySQL, las pruebas de aislamiento
pasarían sin comprobar nada.

---

## La API

Laravel 13 sobre PHP 8.3.

| Carpeta | Qué contiene |
|---|---|
| `app/Models` | Una clase por tabla de negocio |
| `app/Policies` | Qué puede hacer cada rol |
| `app/Services/Tenancy` | El contexto de escuela |
| `app/Services/Planes` | La regla de ausencia y el consumo de clases |
| `app/Services/Agenda` | El choque de horario |
| `app/Services/Recuperacion` | La caducidad y el saldo de la deuda |
| `app/Services/Auth` | El ingreso OIDC |
| `app/Console/Commands` | Verificador de aislamiento y revisiones |

### Las reglas de negocio están en servicios, no en los controladores

Los controladores traducen HTTP. Las decisiones viven en servicios, con nombre
propio, y se pueden probar sin levantar una petición.

`ReglaDeAusencia` existe sola, en un archivo de setenta líneas, porque es la
decisión que más caro sale inventar.

---

## La interfaz

Nuxt 3 con renderizado en el servidor, TypeScript en modo estricto.

Las pantallas son deliberadamente pocas y anchas:

| Pantalla | Para qué |
|---|---|
| `/` | Tablero: qué hay hoy y qué falta por decidir |
| `/alumnos` | Listado y ficha |
| `/agenda` | Quién tiene ocupada cada hora |
| `/asistencia` | Elegir con qué clase empezar a marcar |
| `/clases` | Listado de clases |
| `/clases/{id}` | Toma de asistencia |
| `/planes` | Planes y sus números |
| `/pagos` | Morosidad |
| `/recuperacion` | Deuda de clases |
| `/instrumentos` | Inventario y préstamos |

---

## El flujo de una petición

```
1. El navegador pide /api/v1/alumnos
2. El proxy (Caddy) recibe y reenvía a la interfaz
3. La interfaz reenvía a la API con la cookie de sesión
4. ResolverTenant fija el contexto de escuela en una transacción
5. El controlador pide los datos; PostgreSQL aplica la política RLS
6. La respuesta vuelve por la misma ruta
```

El paso 4 va **antes** que la autenticación a propósito: la política de Row
Level Security necesita el contexto para devolver filas, y si se fijara después
de autenticar, las consultas fallarían.

Ver [03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md).

---

## Lo que NO se hizo, y por qué

### No hay restricción de exclusión para la agenda

Lo natural para impedir el solapamiento sería una restricción de exclusión de
PostgreSQL sobre `tstzrange` con índice GiST. No se puso porque exige la
extensión `btree_gist` y **no se pudo comprobar que esté disponible** en el
entorno de instalación. Se prefirió una comprobación en la aplicación, escrita
y probada, antes que una restricción sin verificar. Está anotado en
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).

### No hay una tabla de roles

Los roles son texto en `usuarios.rol`. Son cuatro y no cambian seguido. Una tabla
de roles obligaría a una migración cada vez que la escuela decide que recepción
también administra, y eso en una PyME pasa seguido.

### No hay envío de correos

Notificar a la familia que su hijo faltó es una función real, pero no se puso
porque no se pudo verificar el proveedor de correo del cliente. La pantalla de
asistencia muestra la información; el envío queda pendiente.

### No hay facturación electrónica

La escuela emite factura, pero emitirla es una integración con el sistema del
contador y no con esta aplicación. `pagos.referencia` guarda el número que la
escuela coloca a mano.