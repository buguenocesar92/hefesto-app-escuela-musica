/**
 * Sesión de la interfaz.
 *
 * QUÉ GUARDA Y QUÉ NO
 * -------------------
 * Solo guarda el usuario: nombre, correo, rol y qué puede hacer. NO guarda la
 * lista de alumnos, ni pagos, ni datos de menores. Cada pantalla pide lo suyo
 * cuando lo necesita, para que la información de un menor no quede en la
 * memoria del navegador más rato del necesario.
 *
 * Por eso `cargar()` no trae el tablero entero: trae lo mínimo para saber si hay
 * alguien dentro y qué puede hacer.
 */

import type { Usuario } from '~/tipos/api';

/** Estado de la sesión. Se comparte entre todas las pantallas. */
const usuario = ref<Usuario | null>(null);
const cargando = ref(false);
const yaConsultado = ref(false);

export function useSesion() {
  /**
   * Consulta quién es, una sola vez por carga de la aplicación.
   *
   * La bandera `yaConsultado` evita repetir la llamada en cada navegación: el
   * middleware de rutas la llama en cada página y sin esta bandera serían
   * tantas peticiones como pantallas visitadas.
   */
  async function cargar(forzar = false): Promise<Usuario | null> {
    if (yaConsultado.value && !forzar) {
      return usuario.value;
    }

    cargando.value = true;

    try {
      const respuesta = await $fetch<{ usuario: Usuario | null }>('/api/v1/auth/usuario');

      usuario.value = respuesta.usuario;
    } catch {
      // Si la API no responde, se trata como "sin sesión". No como un error
      // que rompa la pantalla: la aplicación debe poder mostrar el ingreso
      // aunque el backend esté caído.
      usuario.value = null;
    } finally {
      yaConsultado.value = true;
      cargando.value = false;
    }

    return usuario.value;
  }

  /** Cierra la sesión y limpia el estado local. */
  async function salir(): Promise<void> {
    try {
      await $fetch('/api/v1/auth/logout', { method: 'POST' });
    } finally {
      usuario.value = null;
      yaConsultado.value = true;

      await navigateTo('/entrar');
    }
  }

  /** ¿Hay alguien dentro? */
  function haySesion(): boolean {
    return usuario.value !== null;
  }

  return {
    usuario,
    cargando,
    cargar,
    salir,
    haySesion,
  };
}