/**
 * Tipos de la API, del lado de la interfaz.
 *
 * SON COPIAS DELIBERADAS, NO IMPORTADAS
 * ------------------------------------
 * La interfaz no comparte código con la API: son dos programas separados que
 * se hablan por HTTP. El tipo se replica acá a mano.
 *
 * El costo de esa duplicación es que puede quedar viejo. Se acepta, y por eso
 * el código trata los campos que pueden venir en NULL como opcionales: un tipo
 * que promete más de lo que la API garantiza produce un error en pantalla justo
 * cuando hace falta que la aplicación siga funcionando.
 */

/** Persona con sesión abierta. */
export interface Usuario {
  id: string;
  nombre_completo: string;
  correo: string;
  rol: string;
  rol_texto: string;
  administra: boolean;
  registra_asistencia: boolean;
  ve_pagos: boolean;
}

/** Escuela del selector de ingreso. Solo trae lo que expone la vista pública. */
export interface EscuelaPublica {
  slug: string;
  nombre: string;
}

export interface Alumno {
  id: string;
  nombre: string;
  apellido: string;
  nombre_completo: string;
  activo: boolean;
  tiene_autorizacion: boolean;
}

export interface AlumnoDetalle extends Alumno {
  fecha_ingreso: string | null;
  es_menor_edad: boolean;
  /** Viene en NULL cuando quien pregunta no tiene permiso para verlo. */
  datos_personales: {
    rut: string | null;
    fecha_nacimiento: string | null;
    direccion: string | null;
    telefono: string | null;
    correo: string | null;
  } | null;
}

export interface Plan {
  id: string;
  nombre: string;
  descripcion: string | null;
  clases_incluidas: number | null;
  cupo_definido: boolean;
  precio: number | null;
  precio_definido: boolean;
  vigencia_dias: number | null;
  activo: boolean;
}

export interface Clase {
  id: string;
  nombre: string;
  inicio: string | null;
  termina: string | null;
  estado: string;
  capacidad: number | null;
  docente: { id: string; nombre_completo: string } | null;
  sala: { id: string; nombre: string } | null;
}

/** Estado de la regla que la escuela decide. `null` significa "sin definir". */
export interface ReglaAusencia {
  definida: boolean;
  consume: boolean | null;
  explicacion: string;
}

export interface DeudaRecuperaciones {
  pendientes: number;
  sin_caducidad: number;
  con_vencimiento: number;
}

export interface Recuperacion {
  id: string;
  alumno: string | null;
  clase_origen: string | null;
  estado: string;
  creada_en: string;
  vence_el: string | null;
  /** Verdadero cuando no hay fecha de vencimiento: hay que revisarla a mano. */
  sin_caducidad: boolean;
}

export interface Pago {
  id: string;
  concepto: string;
  concepto_texto: string;
  /** NULL cuando el plan todavía no tiene precio definido. */
  monto: number | null;
  monto_definido: boolean;
  periodo_desde: string | null;
  periodo_hasta: string | null;
  pagado_en: string | null;
  pendiente: boolean;
  metodo: string | null;
  referencia: string | null;
}

export interface Instrumento {
  id: string;
  nombre: string;
  tipo: string | null;
  marca: string | null;
  codigo_interno: string | null;
  estado: string;
  estado_texto: string;
  disponible: boolean;
  prestamos_abiertos: number;
  observacion: string | null;
}

export interface Tablero {
  clases_de_hoy: Array<{
    id: string;
    nombre: string;
    inicio: string | null;
    termina: string | null;
    docente: string | null;
    sala: string | null;
  }>;
  regla_ausencia: ReglaAusencia;
  ausencias_por_definir: {
    cantidad: number;
    ultimas: Array<{ alumno: string | null; registrado_en: string }>;
  };
  deuda_sin_caducidad: { cantidad: number; explicacion: string };
  totales: {
    alumnos_activos: number;
    planes_activos: number;
    recuperaciones_totales: number;
  };
  /** Solo viene si el rol tiene permiso. No se manda en cero. */
  pagos_pendientes?: Array<{
    alumno: string | null;
    monto: number | null;
    monto_definido: boolean;
    periodo_hasta: string | null;
  }>;
}