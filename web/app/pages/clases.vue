<script setup lang="ts">
/**
 * Listado de clases.
 *
 * Al tomar una clase se entra a la pantalla de asistencia, que es donde el
 * docente está realmente trabajando.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { Clase } from '~/tipos/api';

const clases = ref<Clase[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);
const filtroEstado = ref('');

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{ clases: Clase[] }>('/api/v1/clases');
    clases.value = respuesta.clases;
  } catch {
    error.value = 'No se pudo obtener el listado de clases.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);

const visibles = computed(() =>
  filtroEstado.value === ''
    ? clases.value
    : clases.value.filter((clase) => clase.estado === filtroEstado.value)
);

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

function dia(valor: string | null): string {
  if (!valor) {
    return '—';
  }

  return new Date(valor).toLocaleDateString('es-CL', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'America/Santiago',
  });
}
</script>

<template>
  <div>
    <h1>Clases</h1>

    <div class="tarjeta">
      <label for="estado">Filtrar por estado</label>
      <select id="estado" v-model="filtroEstado">
        <option value="">Todas</option>
        <option value="agendada">Agendadas</option>
        <option value="realizada">Realizadas</option>
        <option value="anulada">Anuladas</option>
      </select>
    </div>

    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>
    <p v-if="cargando" class="tenue">Cargando…</p>

    <div v-else class="tarjeta">
      <p v-if="visibles.length === 0" class="sin-datos">No hay clases.</p>

      <table v-else>
        <thead>
          <tr>
            <th scope="col">Fecha</th>
            <th scope="col">Hora</th>
            <th scope="col">Clase</th>
            <th scope="col">Docente</th>
            <th scope="col">Sala</th>
            <th scope="col">Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="clase in visibles" :key="clase.id">
            <td>{{ dia(clase.inicio) }}</td>
            <td>{{ hora(clase.inicio) }} – {{ hora(clase.termina) }}</td>
            <td>{{ clase.nombre }}</td>
            <td>{{ clase.docente?.nombre_completo ?? '—' }}</td>
            <td>{{ clase.sala?.nombre ?? '—' }}</td>
            <td>
              <NuxtLink :to="`/clases/${clase.id}`">Abrir</NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>