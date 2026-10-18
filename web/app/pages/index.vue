<script setup lang="ts">
/**
 * Tablero: lo primero que ve quien abre la aplicación.
 *
 * NO ES UN RESUMEN DE ADORNOS
 * --------------------------
 * Cada bloque responde una pregunta que se hace todos los días: qué clases hay
 * hoy, qué falta por decidir, qué deuda se está acumulando y quién debe.
 *
 * Los bloques que dependen de una regla sin configurar se muestran con su
 * explicación. Si la regla de consumo por ausencia no está definida, el tablero
 * lo dice con todas las letras, en vez de mostrar un cero que parecería un dato.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { Tablero } from '~/tipos/api';

const tablero = ref<Tablero | null>(null);
const error = ref<string | null>(null);
const cargando = ref(true);

const { usuario } = useSesion();

onMounted(async () => {
  try {
    tablero.value = await $fetch<Tablero>('/api/v1/tablero');
  } catch {
    error.value = 'No se pudo cargar el tablero.';
  } finally {
    cargando.value = false;
  }
});

/** Formatea una marca de tiempo en formato chileno, sin pasar por UTC. */
function hora(valor: string | null): string {
  if (!valor) {
    return '—';
  }

  return new Date(valor).toLocaleTimeString('es-CL', {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'America/Santiago',
  });
}

/** Un monto sin definir se dice con palabras, no con un cero. */
function monto(valor: number | null): string {
  if (valor === null) {
    return 'sin monto definido';
  }

  return new Intl.NumberFormat('es-CL', {
    style: 'currency',
    currency: 'CLP',
    maximumFractionDigits: 0,
  }).format(valor);
}
</script>

<template>
  <div>
    <h1>Tablero</h1>

    <p v-if="cargando" class="tenue">Cargando…</p>

    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>

    <template v-if="tablero">
      <!--
        El bloque de la regla va PRIMERO y no es decorativo: si la regla no está
        definida, el resto de los números del tablero están incompletos.
      -->
      <div
        class="aviso"
        :class="tablero.regla_ausencia.definida ? '' : 'pendiente'"
        role="status"
      >
        <p class="titulo-aviso">
          Regla de consumo por ausencia:
          <template v-if="tablero.regla_ausencia.definida">
            {{ tablero.regla_ausencia.consume ? 'la ausencia consume la clase' : 'la ausencia no consume la clase' }}
          </template>
          <template v-else>sin definir</template>
        </p>
        <p>{{ tablero.regla_ausencia.explicacion }}</p>
      </div>

      <div v-if="!tablero.regla_ausencia.definida" class="tarjeta">
        <h2>Ausencias que esperan decisión</h2>
        <p class="tenue chico">
          Estas ausencias quedaron registradas sin consumir ninguna clase del
          plan, porque la regla no está configurada.
        </p>
        <p v-if="tablero.ausencias_por_definir.cantidad === 0" class="sin-datos">
          No hay ausencias pendientes de decisión.
        </p>
        <ul v-else class="chico">
          <li v-for="item in tablero.ausencias_por_definir.ultimas" :key="item.registrado_en">
            {{ item.alumno }} — {{ hora(item.registrado_en) }}
          </li>
        </ul>
      </div>

      <div class="rejilla">
        <div class="tarjeta">
          <h2>Clases de hoy</h2>
          <p v-if="tablero.clases_de_hoy.length === 0" class="sin-datos">
            No hay clases agendadas para hoy.
          </p>
          <table v-else>
            <thead>
              <tr>
                <th scope="col">Hora</th>
                <th scope="col">Clase</th>
                <th scope="col">Docente</th>
                <th scope="col">Sala</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="clase in tablero.clases_de_hoy" :key="clase.id">
                <td>{{ hora(clase.inicio) }}</td>
                <td>{{ clase.nombre }}</td>
                <td>{{ clase.docente ?? '—' }}</td>
                <td>{{ clase.sala ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="tarjeta">
          <h2>Deuda de clases sin fecha</h2>
          <p><strong>{{ tablero.deuda_sin_caducidad.cantidad }}</strong></p>
          <p class="chico tenue">{{ tablero.deuda_sin_caducidad.explicacion }}</p>
          <NuxtLink to="/recuperacion" class="chico">Ver la deuda completa</NuxtLink>
        </div>

        <div class="tarjeta">
          <h2>En números</h2>
          <p class="fila entre">
            <span>Alumnos activos</span>
            <strong>{{ tablero.totales.alumnos_activos }}</strong>
          </p>
          <p class="fila entre">
            <span>Planes activos</span>
            <strong>{{ tablero.totales.planes_activos }}</strong>
          </p>
          <p class="fila entre">
            <span>Recuperaciones pendientes</span>
            <strong>{{ tablero.totales.recuperaciones_totales }}</strong>
          </p>
        </div>
      </div>

      <!--
        El bloque de pagos SOLO existe si el rol tiene permiso. Si la API no lo
        manda, no se dibuja un bloque vacío: no hay nada que ver.
      -->
      <div v-if="tablero.pagos_pendientes" class="tarjeta">
        <h2>Pagos pendientes</h2>
        <p v-if="tablero.pagos_pendientes.length === 0" class="sin-datos">
          No hay pagos pendientes.
        </p>
        <table v-else>
          <thead>
            <tr>
              <th scope="col">Alumno</th>
              <th scope="col">Monto</th>
              <th scope="col">Hasta</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pago in tablero.pagos_pendientes" :key="`${pago.alumno}-${pago.periodo_hasta}`">
              <td>{{ pago.alumno ?? '—' }}</td>
              <td>{{ monto(pago.monto) }}</td>
              <td>{{ pago.periodo_hasta ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>