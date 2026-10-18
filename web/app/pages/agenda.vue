<script setup lang="ts">
/**
 * Agenda por docente y por sala.
 *
 * POR QUÉ LA AGENDA ESTÁ SEPARADA DE LAS CLASES
 * --------------------------------------------
 * La agenda responde "¿quién tiene ocupada esta hora?", y las clases responden
 * "¿qué pasó en esta clase?". Son preguntas distintas y una pantalla que las
 * mezcla obliga a filtrar mentalmente lo que importa.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { Clase } from '~/tipos/api';

const clases = ref<Clase[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);

const desde = ref(new Date().toISOString().slice(0, 10));
const hasta = ref('');

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{ clases: Clase[] }>('/api/v1/clases', {
      query: {
        desde: desde.value,
        hasta: hasta.value || undefined,
      },
    });

    clases.value = respuesta.clases;
  } catch {
    error.value = 'No se pudo obtener la agenda.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);

/** Agrupa por día, para que se lea como un calendario y no como una tabla larga. */
const porDia = computed(() => {
  const grupos = new Map<string, Clase[]>();

  for (const clase of clases.value) {
    const dia = clase.inicio?.slice(0, 10) ?? 'sin fecha';

    if (!grupos.has(dia)) {
      grupos.set(dia, []);
    }

    grupos.get(dia)?.push(clase);
  }

  return Array.from(grupos.entries()).map(([dia, items]) => ({
    dia,
    items,
  }));
});

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

/** Fecha legible, en formato chileno y sin desfase de zona. */
function fecha(dia: string): string {
  if (dia === 'sin fecha') {
    return 'Sin fecha';
  }

  return new Date(`${dia}T12:00:00`).toLocaleDateString('es-CL', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    timeZone: 'America/Santiago',
  });
}
</script>

<template>
  <div>
    <h1>Agenda</h1>

    <div class="tarjeta">
      <form class="fila" @submit.prevent="cargar">
        <div>
          <label for="desde">Desde</label>
          <input id="desde" v-model="desde" type="date" />
        </div>
        <div>
          <label for="hasta">Hasta</label>
          <input id="hasta" v-model="hasta" type="date" />
        </div>
        <button type="submit">Ver</button>
      </form>
    </div>

    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>
    <p v-if="cargando" class="tenue">Cargando…</p>

    <p v-else-if="porDia.length === 0" class="sin-datos">
      No hay clases en el rango elegido.
    </p>

    <div v-for="grupo in porDia" :key="grupo.dia" class="tarjeta">
      <h2>{{ fecha(grupo.dia) }}</h2>

      <table>
        <thead>
          <tr>
            <th scope="col">Hora</th>
            <th scope="col">Clase</th>
            <th scope="col">Docente</th>
            <th scope="col">Sala</th>
            <th scope="col">Estado</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="clase in grupo.items" :key="clase.id">
            <td>{{ hora(clase.inicio) }}</td>
            <td>{{ clase.nombre }}</td>
            <td>{{ clase.docente?.nombre_completo ?? '—' }}</td>
            <td>{{ clase.sala?.nombre ?? '—' }}</td>
            <td>
              <span v-if="clase.estado === 'anulada'" class="aviso alerta chico">
                Anulada
              </span>
              <span v-else>{{ clase.estado }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>