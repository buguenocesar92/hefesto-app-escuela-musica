# 00. Índice

Documentación de la aplicación de gestión de una **escuela de música y arte**.

---

## 01. Arquitectura

Las tres piezas del sistema, qué hace cada una y por qué están separadas.

## 02. Modelo de datos

Las diecisiete tablas de negocio y las decisiones que Took al crearlas,
incluidas las columnas que quedaron en `NULL` a propósito.

## 03. Multitenant y roles

Aislamiento entre escuelas con Row Level Security, y qué puede hacer cada rol.

## 04. Instalación

Cómo poner la aplicación en pie, desde cero, en Linux.

## 05. Configuración

Cada variable de entorno y cada regla de negocio. **Acá está lo importante:**
las tres reglas que el sistema no decide por la escuela.

## 06. Primer alumno

Del arranque a la primera clase impartida, como manual de uso.

## 07. Seguridad y Ley 19.628

Datos personales de menores de edad y la autorización del adulto responsable.

## 08. Respaldos

Cómo respaldar, cómo restaurar, y las cinco reglas que no se rompen.

## 09. API

Rutas, códigos de respuesta y contratos de JSON.

## 10. Pruebas y estado

**El estado real del proyecto.** Nada de esto se ha ejecutado. Quantidades
exactas de lo que falta.

---

## Orden de lectura sugerido

| Si eres... | Lee, en este orden |
|---|---|
| Dirección de la escuela | 06, 02, 05 |
| Persona que instala | 04, 05, 08, 10 |
| Persona que desarrolla | 01, 02, 03, 09, 10 |
| Persona que opera | 06, 08, 10 |

---

## Convenciones de este documento

- Los diagramas van en **ASCII puro**. Sin caracteres de dibujo.
- Los bloques de código llevan el nombre del archivo real.
- Cuando algo no se pudo verificar, se dice **pendiente** y por qué. No se
  rellena el hueco con algo plausible.

---

## Advertencia que se repite en varios documentos

**Nada de este código se ha ejecutado.** No se corrieron migraciones, ni
pruebas, ni se construyeron imágenes. Las cantidades están en
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).