# 09. API

La interfaz habla con la API por `/api/v1`. El proxy es quien entra desde
internet; la API nunca se expone directamente en producción.

**Ninguna ruta de este documento se ha ejecutado.** Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).

---

## Convenciones

| Tema | Decisión |
|---|---|
| Prefijo | `/api/v1` |
| Formato | JSON siempre |
| Sesión | Cookie, no `Authorization` |
| Fechas | ISO 8601 con zona |
| Montos | Pesos enteros, o `null` |
| Rechazo de política | **404**, no 403 |

### El tri-estado viaja como tri-estado

`consume_clase_plan` puede venir `true`, `false` o `null`. Un `null` significa
"la regla no está definida" y **no es lo mismo que `false`**. La interfaz lo
muestra como "Pendiente de decidir".

---

## Rutas abiertas

Estas son las únicas que funcionan sin sesión. Todas están bajo
`/api/v1/auth`, porque la interfaz solo habla con la API por el proxy, que
atiende `/api/**`.

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | `/api/v1/auth/escuelas` | El selector de ingreso. Lee `vw_escuelas_publicas` |
| `GET` | `/api/v1/auth/login` | Devuelve la dirección del proveedor |
| `GET` | `/api/v1/auth/callback` | Abre la sesión |
| `POST` | `/api/v1/auth/logout` | Cierra la sesión |
| `GET` | `/api/v1/salud` | Sondeo de salud. Lo expone el proxy, no la aplicación |

### `GET /api/v1/auth/escuelas`

```json
{
  "escuelas": [
    { "slug": "escuela-aromos", "nombre": "Escuela de Música Los Aromos" }
  ]
}
```

Solo dos columnas. La tabla `escuelas` completa tiene razón social y RUT, y esos
datos no se envían a un formulario abierto.

Las escuelas inactivas no aparecen.

### `GET /api/v1/auth/login`

Devuelve JSON, **no** redirige:

```json
{
  "url_autorizacion": "https://identidad.example.cl/authorize?...",
  "redirigir_a": "/agenda"
}
```

El navegador navega a esa dirección. Se eligió así, y no con una redirección del
servidor, para que la respuesta no dependa de que una cabecera de redirección
atraviese el proxy: eso es un detalle frágil que se rompe sin avisar.

### `GET /api/v1/auth/callback`

Responde JSON con el destino:

```json
{ "mensaje": "Sesión iniciada.", "redirigir_a": "/agenda" }
```

Quien lo convierte en navegación es el proxy de la interfaz
(`web/server/api/[...ruta].ts`), porque en el navegador eso tiene que ser un
cambio de página y no un documento JSON.

Si el estado no coincide, responde **400** y no abre sesión. El estado es un
valor aleatorio guardado en la sesión: sin él, un callback ajeno podría abrir
sesión en el navegador de otra persona.

El destino se valida contra redirecciones abiertas: solo se aceptan rutas
internas que empiezan con una barra y no con `//`. La comprobación está **en las
dos capas**, la API y el proxy.

---

## Sesión

| Método | Ruta |
|---|---|
| `GET` | `/api/v1/auth/usuario` |

```json
{
  "usuario": {
    "id": "9f2c...",
    "nombre_completo": "Persona a Cargo",
    "correo": "direccion@ejemplo.cl",
    "rol": "direccion",
    "rol_texto": "Dirección",
    "administra": true,
    "registra_asistencia": true,
    "ve_pagos": true
  }
}
```

Si no hay sesión, devuelve `{"usuario": null}`.

---

## Tablero

| Método | Ruta |
|---|---|
| `GET` | `/api/v1/tablero` |

Trae las clases de hoy, el estado de la regla de ausencia, las ausencias por
definir, la deuda sin fecha de vencimiento y los totales.

El bloque `pagos_pendientes` **solo viene si el rol tiene permiso**. Si no lo
tiene, la clave **no está en la respuesta**: no viaja en cero ni se esconde para
que la interfaz lo oculte.

---

## Alumnos

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | `/api/v1/alumnos` | Listado. Admite `buscar` y `por_pagina` |
| `POST` | `/api/v1/alumnos` | Alta |
| `GET` | `/api/v1/alumnos/{id}` | Ficha |
| `PUT` | `/api/v1/alumnos/{id}` | Edición |

### El listado no trae datos de contacto

```json
{
  "datos": [
    {
      "id": "3a1f...",
      "nombre": "Camila",
      "apellido": "Pérez",
      "nombre_completo": "Camila Pérez",
      "activo": true,
      "tiene_autorizacion": true
    }
  ]
}
```

Sin dirección, sin teléfono, sin RUT. La lista existe para llamar a la clase.

### La ficha depende del rol

```json
{
  "alumno": {
    "id": "3a1f...",
    "es_menor_edad": true,
    "tiene_autorizacion": false,
    "datos_personales": null
  }
}
```

Cuando `datos_personales` es `null` es porque quien pregunta no tiene permiso,
no porque falten datos. Un docente necesita ver que el alumno existe y cuál es
su saldo, no dónde vive su casa.

---

## Planes

| Método | Ruta |
|---|---|
| `GET` | `/api/v1/planes` |
| `POST` | `/api/v1/planes` |
| `GET` | `/api/v1/planes/{id}` |
| `PUT` | `/api/v1/planes/{id}` |

Los tres números importantes son opcionales y pueden venir `null`:

```json
{
  "id": "7c4e...",
  "nombre": "Mensualidad 4 clases",
  "clases_incluidas": null,
  "cupo_definido": false,
  "precio": null,
  "precio_definido": false,
  "vigencia_dias": null
}
```

`precio_definido: false` no es `precio: 0`. Un cero se lee como "es gratis".

---

## Clases y agenda

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | `/api/v1/clases` | Listado. Admite `desde`, `hasta`, `docente_id` |
| `POST` | `/api/v1/clases` | Crear |
| `GET` | `/api/v1/clases/{id}` | Ficha con la lista de alumnos |
| `PUT` | `/api/v1/clases/{id}` | Editar |

### El choque de horario devuelve 422

```json
{
  "mensaje": "Revise los datos ingresados.",
  "errores": {
    "inicio": [
      "El docente ya tiene una clase en ese horario. La sala ya está ocupada en ese horario. Revise la agenda antes de guardar."
    ]
  }
}
```

El mensaje distingue el motivo, porque "el docente está ocupado" y "la sala está
ocupada" son arreglos distintos.

Dos clases seguidas **no** chocan: una de 18:00 a 19:00 y otra de 19:00 a 20:00
son válidas.

---

## Asistencia

| Método | Ruta |
|---|---|
| `GET` | `/api/v1/clases/{id}/asistencias` |
| `POST` | `/api/v1/clases/{id}/alumnos/{alumno}/asistencia` |

### Registrar

```json
{
  "estado": "ausente",
  "generar_recuperacion": true
}
```

### La respuesta dice qué pasó con el cupo

```json
{
  "mensaje": "Asistencia registrada.",
  "asistencia": {
    "estado": "ausente",
    "consume_clase_plan": null
  },
  "recuperacion": {
    "id": "b8d2...",
    "vence_el": null,
    "sin_caducidad": true
  },
  "regla_ausencia": {
    "definida": false,
    "explicacion": "La regla de consumo por ausencia no está configurada. No se consumió ninguna clase y la ausencia quedó marcada como pendiente."
  },
  "cupo_restante": 4
}
```

`consume_clase_plan: null` significa **"la regla no está definida, no se tocó el
cupo"**. No es lo mismo que `false`.

---

## Pagos

| Método | Ruta | Permiso |
|---|---|---|
| `GET` | `/api/v1/pagos` | Solo los roles de `ESCUELA_MUSICA_ROLES_QUE_VEN_PAGOS` |
| `POST` | `/api/v1/pagos` | Igual |
| `GET` | `/api/v1/alumnos/{id}/pagos` | Igual |
| `POST` | `/api/v1/pagos/{id}/pagado` | Marcar pagado |
| `POST` | `/api/v1/pagos/{id}/anular` | Solo administración |

Un rol sin permiso recibe **404**, no 403.

### El saldo ignora los cargos sin monto

```json
{
  "saldo_pendiente": 0,
  "tiene_montos_sin_definir": true
}
```

`saldo_pendiente: 0` con `tiene_montos_sin_definir: true` quiere decir "hay
cargo, pero no se sabe cuánto vale". Mostrar un saldo de cero sin esa bandera
haría creer que la familia no debe nada.

### Una clase suelta no lleva inscripción

```json
{
  "mensaje": "Un pago por clase suelta no puede llevar una inscripción asociada.",
  "errores": {
    "inscripcion_id": ["Quítela: el pago es por clase suelta."]
  }
}
```

---

## Recuperaciones

| Método | Ruta |
|---|---|
| `GET` | `/api/v1/recuperaciones` |
| `GET` | `/api/v1/recuperaciones/resumen` |
| `GET` | `/api/v1/alumnos/{id}/recuperaciones` |

El resumen separa las dos clases de deuda:

```json
{
  "total_pendientes": 14,
  "total_sin_caducidad": 11,
  "explicacion": "Hay clases recuperadas sin fecha de vencimiento porque la escuela no configuró ESCUELA_MUSICA_DIAS_RECUPERACION. Esas no caducan solas: hay que revisarlas."
}
```

La `explicación` viene en la respuesta para que la interfaz no tenga que
inventarla.

---

## Instrumentos

| Método | Ruta | Qué hace |
|---|---|---|
| `GET` | `/api/v1/instrumentos` | Listado |
| `POST` | `/api/v1/instrumentos` | Alta |
| `GET` | `/api/v1/instrumentos/{id}` | Ficha con los préstamos |
| `POST` | `/api/v1/instrumentos/{id}/prestar` | Entrega |
| `POST` | `/api/v1/prestamos/{id}/devolver` | Devolución |
| `GET` | `/api/v1/alumnos/{id}/prestamos` | Préstamos de un alumno |

### Un instrumento que no está disponible no se entrega

```json
{
  "mensaje": "El instrumento no está disponible para entregar.",
  "errores": {
    "instrumento": ["Estado actual: En mantención."]
  }
}
```

### La devolución decide si queda disponible

| `estado_devolucion` | Instrumento queda en |
|---|---|
| `bueno` | `disponible` |
| `con_danos` | `mantencion` |
| `incompleto` | `mantencion` |

Dejar disponible un instrumento que vuelve roto significaría ofrecerle a otro
alumno algo que está roto.

---

## Códigos de respuesta

| Código | Cuándo |
|---|---|
| `200` | Todo bien |
| `201` | Recurso creado |
| `401` | Sin sesión |
| `404` | No existe, **o** la política lo rechazó |
| `422` | Los datos no pasan la validación |
| `502` | La API no respondió (lo agrega el proxy) |

### El 404 es deliberado

Un `403` confirmaría que el identificador existe en alguna parte. Con 404, no se
puede aprender nada por fuerza bruta: ni cuántos alumnos tiene otra escuela, ni
que un identificador está en uso.

El costo de que un error de permisos y un error de no existencia se vean igual
es un precio aceptable a cambio de no confirmar nada.

---

## Formato de error

```json
{
  "mensaje": "Revise los datos ingresados.",
  "errores": {
    "campo": ["Qué está mal con este campo."]
  }
}
```

Los mensajes vienen en español de Chile, con tildes, y son específicos: "El
docente ya tiene una clase en ese horario" y no "Error de validación".