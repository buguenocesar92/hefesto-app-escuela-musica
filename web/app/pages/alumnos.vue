<script setup lang="ts">
/**
 * Listado de alumnos.
 *
 * LO QUE NO SE MUESTRA EN LA LISTA
 * -------------------------------
 * Ni la dirección ni el teléfono. La lista existe para llamar a la clase; ver
 * dónde vive la familia de un menor no ayuda a eso. Los datos personales están
 * en la ficha, y solo si quien entra tiene permiso.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { Alumno } from '~/tipos/api';

const alumnos = ref<Alumno[]>([]);
const busqueda = ref('');
const cargando = ref(true);
const error = ref<string | null>(null);
const total = ref(0);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{
      datos: Alumno[];
      meta: { total: number };
    }>('/api/v1/alumnos', {
      query: { buscar: busqueda.value || undefined },
    });

    alumnos.value = respuesta.datos;
    total.value = respuesta.meta.total;
  } catch (e: unknown) {
    // Un 404 acá significa que la escuela no tiene permiso sobre la lista. No
    // se muestra el identificador del error: el texto de la API es el que vale.
    error.value = 'No se pudo obtener el listado de alumnos.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);
</script>

<template>
  <div>
    <h1>Alumnos</h1>

    <div class="tarjeta">
      <form class="fila" @submit.prevent="cargar">
        <label class="solo-lectores" for="buscar">Buscar alumno</label>
        <input
          id="buscar"
          v-model="busqueda"
          type="search"
          placeholder="Nombre, apellido o RUT"
        />
        <button type="submit">Buscar</button>
      </form>
    </div>

    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>
    <p v-if="cargando" class="tenue">Cargando…</p>

    <div v-else class="tarjeta">
      <p class="chico tenue">
        {{ total }} alumno(s) encontrado(s).
      </p>

      <p v-if="alumnos.length === 0" class="sin-datos">
        No hay alumnos que coincidan con la búsqueda.
      </p>

      <table v-else>
        <thead>
          <tr>
            <th scope="col">Nombre</th>
            <th scope="col">Alumno activo</th>
            <th scope="col">Autorización registrada</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="alumno in alumnos" :key="alumno.id">
            <td>
              <NuxtLink :to="`/alumnos/${alumno.id}`">{{ alumno.nombre_completo }}</NuxtLink>
            </td>
            <td>{{ alumno.activo ? 'Sí' : 'No' }}</td>
            <td>
              <!--
                El texto acompaña al color: "Sin registrar" en rojo no se lee
                solo por el color.
              -->
              <span v-if="alumno.tiene_autorizacion">Registrada</span>
              <span v-else class="aviso pendiente chico">Sin registrar</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>