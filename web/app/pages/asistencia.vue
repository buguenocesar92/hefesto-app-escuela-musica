<script setup lang="ts">
/**
 * Pantalla de asistencia: elegir con qué clase empezar.
 *
 * POR QUÉ HAY UNA PANTALLA DE SELECCIÓN Y NO UNA LISTA DIRECTA
 * -----------------------------------------------------------
 * La asistencia se registra sobre una clase concreta, no sobre un alumno
 * suelto: importa quién estaba en la sala, no quién falta en general.
 *
 * Esta pantalla lista las clases que se pueden tomar y lleva a la pantalla de
 * marcado, que es `/clases/{id}`. Marcar sobre el alumno suelto obligaría a
 * elegir la clase en cada fila, y con veinte personas en la sala eso es un
 * error esperando.
 *
 * CUANDO SE ABRE UNA PANTALLA
 * ---------------------------
 * Se muestran primero las clases de hoy y, después, las próximas. La regla es
 * que la de hoy se marque apenas llega el docente, no al final del día.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { Clase } from '~/tipos/api';

const clases = ref<Clase[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{ clases: Clase[] }>('/api/v1/clases');
    clases.value = respuesta.clases;
  } catch {
    error.value = 'No se pudo cargar el listado de clases.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);

const hoy = new Date().toISOString().slice(0, 10);

const clasesDeHoy = computed(() =>
  clases.value.filter((clase) => (clase.inicio ?? '').slice(0, 10) === hoy)
);

const siguientes = computed(() =>
  clases.value.filter(
    (clase) =>
      (clase.inicio ?? '').slice(0, 10) > hoy && clase.estado !== 'anulada'
  )
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
</script>

<template>
  <div>
    <h1>Tomar asistencia</h1>

    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>
    <p v-if="cargando" class="tenue">Cargando…</p>

    <template v-else>
      <div class="tarjeta">
        <h2>Clases de hoy</h2>

        <p v-if="clasesDeHoy.length === 0" class="sin-datos">
          No hay clases agendadas para hoy.
        </p>

        <ul v-else class="lista">
          <li v-for="clase in clasesDeHoy" :key="clase.id">
            <div class="fila entre">
              <div>
                <strong>{{ clase.nombre }}</strong>
                <span class="tenue">
                  {{ hora(clase.inicio) }} – {{ hora(clase.termina) }} ·
                  {{ clase.docente?.nombre_completo ?? 'sin docente' }} ·
                  {{ clase.sala?.nombre ?? 'sin sala' }}
                </span>
              </div>
              <NuxtLink :to="`/clases/${clase.id}`" class="boton">Tomar asistencia</NuxtLink>
            </div>
          </li>
        </ul>
      </div>

      <div class="tarjeta">
        <h2>Próximas clases</h2>

        <p v-if="siguientes.length === 0" class="sin-datos">
          No hay clases agendadas más adelante.
        </p>

        <ul v-else class="lista">
          <li v-for="clase in siguientes" :key="clase.id">
            <div class="fila entre">
              <div>
                <strong>{{ clase.nombre }}</strong>
                <span class="tenue">
                  {{ (clase.inicio ?? '').slice(0, 10) }} ·
                  {{ hora(clase.inicio) }} ·
                  {{ clase.docente?.nombre_completo ?? 'sin docente' }}
                </span>
              </div>
              <NuxtLink :to="`/clases/${clase.id}`" class="boton secundario">
                Abrir
              </NuxtLink>
            </div>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>

<style scoped>
.lista {
  list-style: none;
  margin: 0;
  padding: 0;
}

.lista li {
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--borde);
}

.lista li:last-child {
  border-bottom: none;
}
</style>