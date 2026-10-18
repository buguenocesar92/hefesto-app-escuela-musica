<script setup lang="ts">
/**
 * Instrumentos: inventario, préstamo y devolución.
 *
 * LO QUE INTERESA VER
 * -------------------
 * Un instrumento que no volvió. Es lo primero que se busca cuando alguien
 * reclama, así que la columna de préstamos abiertos está a la vista y no
 * escondida en la ficha.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { Instrumento } from '~/tipos/api';

const instrumentos = ref<Instrumento[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);
const filtro = ref('');

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{ instrumentos: Instrumento[] }>('/api/v1/instrumentos', {
      query: { estado: filtro.value || undefined },
    });

    instrumentos.value = respuesta.instrumentos;
  } catch {
    error.value = 'No se pudo cargar el inventario.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
watch(filtro, cargar);

const faltantes = computed(() =>
  instrumentos.value.filter((i) => i.prestamos_abiertos > 0).length
);

/** Estado con texto, para que el color no sea el único indicio. */
function estado(item: Instrumento): string {
  if (item.estado === 'disponible' && item.prestamos_abiertos > 0) {
    return 'Disponible (tiene un préstamo abierto)';
  }

  return item.estado_texto;
}
</script>

<template>
  <div>
    <h1>Instrumentos</h1>

    <div v-if="faltantes > 0" class="aviso pendiente">
      <p class="titulo-aviso">{{ faltantes }} instrumento(s) con préstamo abierto</p>
      <p>
        Hay instrumentos que todavía no fueron devueltos. Si no aparecen
        devueltos, conviene revisar con la familia.
      </p>
    </div>

    <div class="tarjeta">
      <label for="estado">Filtrar por estado</label>
      <select id="estado" v-model="filtro">
        <option value="">Todos</option>
        <option value="disponible">Disponibles</option>
        <option value="prestado">Prestados</option>
        <option value="arrendado">Arrendados</option>
        <option value="mantencion">En mantención</option>
        <option value="baja">De baja</option>
      </select>
    </div>

    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>
    <p v-if="cargando" class="tenue">Cargando…</p>

    <div v-else class="tarjeta">
      <p v-if="instrumentos.length === 0" class="sin-datos">
        No hay instrumentos registrados.
      </p>

      <table v-else>
        <thead>
          <tr>
            <th scope="col">Instrumento</th>
            <th scope="col">Tipo</th>
            <th scope="col">Código interno</th>
            <th scope="col">Estado</th>
            <th scope="col">Préstamos abiertos</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in instrumentos" :key="item.id">
            <td>{{ item.nombre }}</td>
            <td>{{ item.tipo ?? '—' }}</td>
            <td>{{ item.codigo_interno ?? '—' }}</td>
            <td>
              <span v-if="item.estado === 'mantencion'" class="aviso alerta chico">
                {{ estado(item) }}
              </span>
              <span v-else class="chico">{{ estado(item) }}</span>
            </td>
            <td>
              <span v-if="item.prestamos_abiertos > 0" class="aviso pendiente chico">
                {{ item.prestamos_abiertos }}
              </span>
              <span v-else class="tenue">0</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>