# 06. Primer alumno

Manual de uso, del arranque a la primera clase impartida.

**Nada de esto se ha probado con usuarios reales.** Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).

---

## 1. Lo primero: las reglas que hay que decidir

Antes de registrar al primer alumno, hay que decidir las tres reglas de
negocio. No es un trámite: si no se deciden, el sistema funciona pero deja
trabajo pendiente en cada clase.

| Pregunta | Variable | Qué pasa si no se decide |
|---|---|---|
| ¿La ausencia consume la clase del plan? | `ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN` | Ninguna ausencia consume nada y todas quedan marcadas como pendientes |
| ¿Cuánto vive una clase recuperada? | `ESCUELA_MUSICA_DIAS_RECUPERACION` | Las clases recuperadas no caducan nunca |
| ¿Con cuántos días se avisa de un plan por vencer? | `ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD` | No hay aviso, pero la lista de morosos se ve igual |

La segunda es la que más conversaciones genera con las familias. Una escuela
que no la define tiene una deuda de clases que crece sin que nadie la vea, y en
algún momento aparece una familia que reclama una clase de hace dos años.

Detalle en [05-CONFIGURACION.md](05-CONFIGURACION.md).

---

## 2. Crear el primer alumno

1. Entre a **Alumnos**.
2. Complete nombre y apellido. Son los únicos campos obligatorios.
3. El RUT, la fecha de nacimiento y la dirección se pueden dejar vacíos.

**Por qué son casi todos opcionales:** una familia puede empezar clases antes de
entregar el documento, y bloquear el alta produce el resultado contrario: la
escuela anotando al menor en un cuaderno al lado, que es peor que un campo en
blanco en el sistema.

La fecha de nacimiento sí conviene pedirla: de ella depende saber si el alumno
es menor de edad, y eso cambia cómo se tratan sus datos.

---

## 3. Si el alumno es menor de edad

La ficha avisa en rojo cuando el alumno es menor y no hay ningún responsable con
la autorización registrada.

Eso no es burocracia. Los datos personales de un menor no se pueden tratar solo
porque la escuela tenga su autorización: hace falta la del adulto responsable.

Para registrarlo:

1. Cree el **responsable** con su nombre, su relación con el alumno y un medio de
   contacto.
2. Vincule el responsable al alumno.
3. Marque que **la autorización está registrada** y anote la fecha.

Mientras no haya autorización registrada, la ficha muestra el aviso. Con
`ESCUELA_MUSICA_EXIGIR_AUTORIZACION=true`, que es el valor por omisión, el
sistema no deja dar de alta clases a un menor sin ese vínculo.

La fecha de vencimiento de la autorización queda en `NULL` a propósito: es una
decisión de la escuela con su responsable legal, y el sistema no la inventa. Ver
[07-SEGURIDAD-Y-LEY-19628.md](07-SEGURIDAD-Y-LEY-19628.md).

---

## 4. Crear un plan

1. Entre a **Planes** y cree uno.
2. Defina la cantidad de clases y el precio.

Si el precio queda sin definir, la pantalla lo dice y el sistema no genera cobros
con monto. Es preferible a tener un precio de ejemplo que termine en una
factura.

`precio_congelado` guarda el precio al momento de contratar. Si el precio del
plan sube en enero, las inscripciones de diciembre siguen mostrando lo que se
cobró en diciembre.

---

## 5. Inscribir al alumno

La inscripción es el vínculo entre el alumno y el plan.

**La decisión es por alumno, no por curso.** Dos alumnos en la misma clase
pueden estar uno con plan y otro pagando clase por clase. Quien paga mensualidad
tiene clases disponibles; quien paga suelto, no.

Una inscripción **suspendida** no habilita consumo: si alguien pausa su
mensualidad, no debería verle gastar el cupo de un mes que no está pagando.

---

## 6. Crear la sala y el docente

**Salas** y **Docentes** se crean en administración. En esta versión no hay
pantallas separadas para ellos: se registran como parte del alta inicial y
quedan para el siguiente despliegue de la interfaz.

---

## 7. Crear la clase en la agenda

1. Entre a **Clases** y cree una.
2. Elija docente, sala, día y hora.

La agenda **rechaza el choque de horario**, y el mensaje dice cuál de las dos
cosas hay que mover:

- "El docente ya tiene una clase en ese horario."
- "La sala ya está ocupada en ese horario."

Dos clases seguidas no chocan: una de 18:00 a 19:00 y otra de 19:00 a 20:00 son
válidas.

La comprobación la hace la aplicación, no la base de datos. Ver la nota sobre
`btree_gist` en [01-ARQUITECTURA.md](01-ARQUITECTURA.md).

---

## 8. Tomar la asistencia

Entre a la clase y marque cada alumno.

Debajo de cada marcado aparece el efecto real:

| Lo que aparece | Qué pasó |
|---|---|
| "Consumió la clase" | La ausencia descontó del plan |
| "No consumió la clase" | La ausencia no descontó nada |
| "Pendiente de decidir" | La regla no está configurada. **No se tocó el cupo** |

Lo importante: si la regla no está configurada, el docente ve
**"Pendiente de decidir"** y no un mensaje que diga que no se descontó nada. La
diferencia es que una es una decisión de la escuela y la otra parece un hecho.

Una ausencia genera una recuperación, con su fecha de vencimiento calculada, o
sin fecha si no hay caducidad configurada.

---

## 9. Ver la deuda de clases

La pantalla **Recuperación** separa las dos clases de deuda:

- las que tienen fecha de vencimiento;
- las que **no** tienen fecha y hay que revisar a mano.

La segunda categoría es la que importa. Si la escuela no configuró la caducidad,
esas clases no vencen solas y la deuda se acumula.

Para repasar:

```bash
php artisan escuela-musica:vencer-recuperaciones
```

---

## 10. Registrar un pago

Los pagos son de dos tipos:

| Concepto | Cuándo |
|---|---|
| Cuota de plan | La mensualidad del período |
| Clase suelta | Cuando el alumno no tiene plan |

Una clase suelta **no puede llevar inscripción asociada**: se paga suelta
precisamente porque no hay plan. Si se manda una, la API lo rechaza con 422.

`pagado_en` en `NULL` significa pendiente. Para marcar un pago como hecho está
la operación correspondiente.

**Un cobro mal hecho se anula, no se borra.** La fila queda con `anulado` en
verdadero. Que alguien haya cobrado y después se anulara vale más como dato que
la ausencia del registro.

---

## 11. Prestar un instrumento

En **Instrumentos**:

1. Entrega: se elige el alumno y el tipo, préstamo o arriendo.
2. Al devolver: se marca cómo volvió.

Si vuelve con daños o incompleto, el instrumento pasa a **mantención**. Dejarlo
disponible significaría ofrecerle a otro alumno algo que está roto.

El préstamo no se borra al devolver: queda con su `estado_devolucion`. Ese
registro es lo que sirve para reclamar cuando algo falta.

---

## 12. El tablero, cada mañana

El tablero muestra cuatro cosas:

1. **Las clases de hoy**, en orden.
2. **La regla de consumo**: si está definida o no.
3. **Las ausencias por definir**, si hay.
4. **La deuda de clases sin fecha**, si hay.

Y el bloque de pagos, solo si el rol tiene permiso.

El bloque 4 es el que evita el problema que no se ve: una deuda que crece sin
que nadie la mire deja de ser un problema de las familias y pasa a ser un
problema de la escuela.