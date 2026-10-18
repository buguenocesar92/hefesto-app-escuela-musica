<script setup lang="ts">
/**
 * Planes mensuales.
 *
 * CUANDO NO HAY PRECIO, LA PANTALLA LO DICE
 * ----------------------------------------
 * Un plan sin precio definido muestra "sin definir" y no un cero. Un cero se
 * lee como "es gratis", que es una afirmación que el sistema no puede hacer.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { Plan } from '~/tipos/api';

const planes = ref<Plan[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);

const { usuario } = useSesion();

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{ planes: Plan[] }>('/api/v1/planes');
    planes.value = respuesta.planes;
  } catch {
    error.value = 'No se pudo obtener el listado de planes.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);

function monto(valor: number | null): string {
  if (valor === null) {
    return 'sin definir';
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
    <h1>Planes</h1>

    <div v-if="!planes.some((p) => p.precio_definido || p.cupo_definido)" class="aviso pendiente">
      <p class="titulo-aviso">Ningún plan tiene precio o cupo definido</p>
      <p>
        Mientras el precio y la cantidad de clases no estén definidos, el
        sistema no calcula saldos ni genera cobros con monto. Es preferible a
        mostrar una cifra que nadie definió.
      </p>
    </div>

    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>
    <p v-if="cargando" class="tenue">Cargando…</p>

    <div v-else class="tarjeta">
      <p v-if="planes.length === 0" class="sin-datos">
        No hay planes registrados.
      </p>

      <table v-else>
        <thead>
          <tr>
            <th scope="col">Plan</th>
            <th scope="col">Clases incluidas</th>
            <th scope="col">Precio</th>
            <th scope="col">Vigencia</th>
            <th scope="col">Estado</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="plan in planes" :key="plan.id">
            <td>{{ plan.nombre }}</td>
            <td>
              <span v-if="plan.cupo_definido">{{ plan.clases_incluidas }}</span>
              <span v-else class="tenue">sin definir</span>
            </td>
            <td>{{ monto(plan.precio) }}</td>
            <td>
              <span v-if="plan.vigencia_dias !== null">{{ plan.vigencia_dias }} días</span>
              <span v-else class="tenue">sin definir</span>
            </td>
            <td>{{ plan.activo ? 'Activo' : 'Inactivo' }}</td>
          </tr>
        </tbody>
      </table>

      <p v-if="usuario?.administra" class="chico tenue" style="margin-top: 1rem">
        Los precios los define la dirección de la escuela. Esta pantalla solo
        muestra lo que hay cargado.
      </p>
    </div>
  </div>
</template>