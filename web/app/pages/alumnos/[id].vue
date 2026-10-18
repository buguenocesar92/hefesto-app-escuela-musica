<script setup lang="ts">
/**
 * Ficha de un alumno.
 *
 * LOS DATOS PERSONALES SON CONDICIONALES
 * --------------------------------------
 * El bloque con RUT, fecha de nacimiento, dirección y teléfono solo se pide si
 * quien entra tiene permiso (`datos_personales`). Un docente ve que el alumno
 * existe y cuál es su saldo, no dónde vive su casa.
 *
 * Cuando el alumno es menor de edad, la pantalla recuerda si la autorización del
 * adulto responsable está registrada. No es un detalle administrativo: es lo
 * que permite tratar sus datos.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { AlumnoDetalle, DeudaRecuperaciones } from '~/tipos/api';

interface RespuestaFicha {
  alumno: AlumnoDetalle;
  plan: { cupo_restante: number | null; clases_consumidas: number };
  recuperaciones: DeudaRecuperaciones;
}

const ruta = useRoute();
const alumnoId = ruta.params.id as string;

const ficha = ref<RespuestaFicha | null>(null);
const cargando = ref(true);
const error = ref<string | null>(null);

onMounted(async () => {
  try {
    ficha.value = await $fetch<RespuestaFicha>(`/api/v1/alumnos/${alumnoId}`);
  } catch (e: unknown) {
    // Un 404 puede significar dos cosas: que el alumno no existe o que es de
    // otra escuela. La pantalla no distingue, porque distinguirlo confirmaría
    // que el identificador existe en algún lado.
    error.value = 'No se pudo cargar la ficha del alumno.';
  } finally {
    cargando.value = false;
  }
});

function saldo(valor: number | null): string {
  if (valor === null) {
    return 'sin plan con cupo definido';
  }

  return `${valor} clase(s) disponibles`;
}
</script>

<template>
  <div>
    <p><NuxtLink to="/alumnos">Volver a los alumnos</NuxtLink></p>

    <p v-if="cargando" class="tenue">Cargando…</p>
    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>

    <template v-if="ficha">
      <h1>{{ ficha.alumno.nombre_completo }}</h1>

      <!--
        Aviso de autorización. Aparece solo si el alumno es menor de edad: es
        el caso en que el tratamiento de datos depende del adulto responsable.
      -->
      <div
        v-if="ficha.alumno.es_menor_edad && !ficha.alumno.tiene_autorizacion"
        class="aviso alerta"
        role="status"
      >
        <p class="titulo-aviso">Falta la autorización del adulto responsable</p>
        <p>
          Este alumno es menor de edad y no tiene un responsable con la
          autorización registrada. Sin ella, los datos del menor no deberían
          estar tratados.
        </p>
      </div>

      <div v-else-if="ficha.alumno.es_menor_edad" class="aviso">
        <p class="titulo-aviso">Autorización registrada</p>
        <p>El menor tiene un adulto responsable con la autorización al día.</p>
      </div>

      <div class="rejilla">
        <div class="tarjeta">
          <h2>Plan</h2>
          <p>{{ saldo(ficha.plan.cupo_restante) }}</p>
          <p class="chico tenue">
            {{ ficha.plan.clases_consumidas }} clase(s) consumida(s) en el
            período actual.
          </p>
        </div>

        <div class="tarjeta">
          <h2>Deuda de clases</h2>
          <p><strong>{{ ficha.recuperaciones.pendientes }}</strong> pendiente(s)</p>
          <p class="chico tenue">
            {{ ficha.recuperaciones.sin_caducidad }} sin fecha de vencimiento.
          </p>
          <p
            v-if="ficha.recuperaciones.sin_caducidad > 0"
            class="aviso pendiente chico"
          >
            Estas clases no caducan: hay que acordarlas a mano.
          </p>
        </div>
      </div>

      <div class="tarjeta">
        <h2>Datos personales</h2>

        <p v-if="!ficha.alumno.datos_personales" class="sin-datos">
          Su rol no tiene permiso para ver los datos personales de los alumnos.
        </p>

        <table v-else>
          <tbody>
            <tr>
              <th scope="row">RUT</th>
              <td>{{ ficha.alumno.datos_personales.rut ?? 'sin registrar' }}</td>
            </tr>
            <tr>
              <th scope="row">Fecha de nacimiento</th>
              <td>{{ ficha.alumno.datos_personales.fecha_nacimiento ?? 'sin registrar' }}</td>
            </tr>
            <tr>
              <th scope="row">Dirección</th>
              <td>{{ ficha.alumno.datos_personales.direccion ?? 'sin registrar' }}</td>
            </tr>
            <tr>
              <th scope="row">Teléfono</th>
              <td>{{ ficha.alumno.datos_personales.telefono ?? 'sin registrar' }}</td>
            </tr>
            <tr>
              <th scope="row">Correo</th>
              <td>{{ ficha.alumno.datos_personales.correo ?? 'sin registrar' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>