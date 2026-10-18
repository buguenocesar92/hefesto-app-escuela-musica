# Escuela de Música y Arte

Aplicación de gestión para una escuela de música y arte: quién viene, quién
falta y quién pagó.

Es **una escuela, no un colegio**. No hay plan de estudios, ni notas, ni
conducta escolar. Lo que hay es una lista de alumnos, una agenda de clases con
docente y sala, asistencia, clases recuperadas, pagos e inventario de
instrumentos.

---

## Lo que este sistema se propone resolver

| Problema | Dónde se resuelve |
|---|---|
| Distinguir ingreso estable de ingreso variable, por alumno | `planes`, `inscripciones`, `pagos` |
| Que un docente no esté en dos lugares a la vez | `App\Services\Agenda\DetectorDeSolapamiento` |
| Que una ausencia consuma o no la clase del plan | `App\Services\Planes\ReglaDeAusencia` |
| Que la clase recuperada caduque, o que la deuda se vea | `App\Services\Recuperacion\` |
| Ver quién quedó moroso antes de que sea un problema | `escuela-musica:revisar-morosidad` |
| Saber qué instrumento prestó y cómo volvió | `instrumentos`, `prestamos_instrumento` |

---

## Las tres reglas que el sistema NO inventa

Este es el punto más importante de todo el proyecto. Hay tres decisiones que son
de la escuela y que no se pueden deducir de nada:

1. **¿Una ausencia consume la clase del plan?** Configurable en
   `ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN`. Sin definir, la ausencia se registra
   pero **no consume nada** y queda marcada como pendiente de decisión.

2. **¿Cuántos días vive una clase recuperada?**
   `ESCUELA_MUSICA_DIAS_RECUPERACION`. Sin definir, la recuperación **no
   caduca** y la deuda aparece a la vista para revisarla a mano.

3. **¿Con cuántos días se avisa de un plan por vencer?**
   `ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD`. Sin definir, no hay aviso, pero la
   lista completa de morosos se sigue viendo.

Las tres variables vienen **vacías** en `.env.example` y no tienen un valor por
omisión en el código. Preferimos el hueco al dato inventado.

Los precios de los planes tampoco tienen valor por omisión: `planes.precio`
admite `NULL`, y un plan sin precio no genera cobros con monto.

---

## Estado de esto

**Nada de este código se ha ejecutado.** En la máquina donde se escribió no
había PHP, Composer, Node, Docker ni PostgreSQL. Cero migraciones aplicadas, cero
pruebas corridas, cero imágenes construidas.

Los detalles están en [docs/10-PRUEBAS-Y-ESTADO.md](docs/10-PRUEBAS-Y-ESTADO.md).

---

## Aislamiento entre escuelas

Cada fila de negocio lleva `tenant_id` y está protegida por Row Level Security
de PostgreSQL, con `USING` y `WITH CHECK` y con `FORCE ROW LEVEL SECURITY`. El
contexto se fija con `set_config(..., true)`, es decir, con alcance de
**transacción**, y la función de contexto **lanza excepción** cuando no hay
escuela: el aislamiento falla cerrado, nunca abierto.

Se comprueba con:

```
php artisan escuela-musica:verificar-aislamiento
```

Detalle en [docs/03-MULTITENANCY-Y-ROLES.md](docs/03-MULTITENANCY-Y-ROLES.md).

---

## Documentación

| Documento | De qué trata |
|---|---|
| [00-INDICE.md](docs/00-INDICE.md) | Índice y orden de lectura |
| [01-ARQUITECTURA.md](docs/01-ARQUITECTURA.md) | Las tres piezas y por qué |
| [02-MODELO-DE-DATOS.md](docs/02-MODELO-DE-DATOS.md) | Tablas y sus decisiones |
| [03-MULTITENANCY-Y-ROLES.md](docs/03-MULTITENANCY-Y-ROLES.md) | Aislamiento y roles |
| [04-INSTALACION.md](docs/04-INSTALACION.md) | Instalación paso a paso |
| [05-CONFIGURACION.md](docs/05-CONFIGURACION.md) | Variables y reglas de negocio |
| [06-PRIMER-ALUMNO.md](docs/06-PRIMER-ALUMNO.md) | Del arranque a la primera clase |
| [07-SEGURIDAD-Y-LEY-19628.md](docs/07-SEGURIDAD-Y-LEY-19628.md) | Datos de menores |
| [08-RESPALDOS.md](docs/08-RESPALDOS.md) | Respaldo y restauración |
| [09-API.md](docs/09-API.md) | Rutas y contratos |
| [10-PRUEBAS-Y-ESTADO.md](docs/10-PRUEBAS-Y-ESTADO.md) | **Lo que no se ha probado** |

---

## Estructura

```
apps/escuela-musica/
  api/       Laravel 13 + PHP 8.3 + PostgreSQL
  web/       Nuxt 3 + TypeScript
  deploy/    Docker, proxy y respaldos
  docs/      Esta documentación
```