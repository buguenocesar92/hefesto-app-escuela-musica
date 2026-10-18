<script setup lang="ts">
/**
 * Deuda de clases recuperadas.
 *
 * EL BLOQUE MÁS IMPORTANTE DE LA APLICACIÓN
 * -----------------------------------------
 * Las recuperaciones sin fecha de vencimiento NO caducan. Si esta pantalla no
 * las separa, la deuda se acumula en silencio y la escuela termina debiendo
 * clases que ya nadie recuerda.
 *
 * Por eso el resumen va arriba y distingue las dos clases de deuda. La primera
 * columna de la tabla, "Sin caducidad", es la que hay que mirar en cada reunión.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { Recuperacion } from '~/tipos/api';

interface ResumenDeuda {
  recuperaciones: Recuperacion[];
  resumen: { total: number; sin_caducidad: number };
}

const datos = ref<ResumenDeuda | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

const soloSinCaducidad = ref(false);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    datos.value = await $fetch<ResumenDeuda>('/api/v1/recuperaciones', {
      query: { estado: soloSinCaducidad.value ? 'pendiente' : undefined },
    });
  } catch {
    error.value = 'No se pudo cargar la deuda de recuperaciones.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
watch(soloSinCaducidad, cargar);

const sinCaducidad = computed(() =>
  (datos.value?.recuperaciones ?? []).filter((r) => r.sin_caducidad).length
);

function fecha(valor: string | null): string {
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
    <h1>Recuperación de clases</h1>

    <div v-if="datos && datos.resumen.sin_caducidad > 0" class="aviso pendiente">
      <p class="titulo-aviso">
        Hay {{ datos.resumen.sin_caducidad }} clase(s) sin fecha de vencimiento
      </p>
      <p>
        La escuela no configuró un plazo de caducidad. Estas clases no vencen
        solas y hay que acordarlas con la familia a mano. Sin esto, la deuda se
        acumula sin que nadie la vea.
      </p>
    </div>

    <div class="tarjeta">
      <div class="fila">
        <input id="sin-caducidad" v-model="soloSinCaducidad" type="checkbox" />
        <label for="sin-caducidad">Mostrar solo las pendientes</label>
      </div>
    </div>

    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>
    <p v-if="cargando" class="tenue">Cargando…</p>

    <div v-else-if="datos" class="tarjeta">
      <p class="chico tenue">
        {{ datos.resumen.total }} recuperacion(es) en total;
        {{ sinCaducidad }} sin fecha de vencimiento.
      </p>

      <p v-if="datos.recuperaciones.length === 0" class="sin-datos">
        No hay clases pendientes de recuperar.
      </p>

      <table v-else>
        <thead>
          <tr>
            <th scope="col">Alumno</th>
            <th scope="col">Clase que se perdió</th>
            <th scope="col">Creada</th>
            <th scope="col">Vence</th>
            <th scope="col">Estado</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in datos.recuperaciones" :key="item.id">
            <td>{{ item.alumno ?? '—' }}</td>
            <td>{{ item.clase_origen ?? '—' }}</td>
            <td>{{ fecha(item.creada_en) }}</td>
            <td>
              <span v-if="item.sin_caducidad" class="aviso pendiente chico">
                Sin caducidad
              </span>
              <span v-else>{{ fecha(item.vence_el) }}</span>
            </td>
            <td>{{ item.estado }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>