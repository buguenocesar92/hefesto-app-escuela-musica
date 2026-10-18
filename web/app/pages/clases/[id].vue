<script setup lang="ts">
/**
 * Toma de asistencia de una clase.
 *
 * LA PANTALLA QUE MÁS IMPORTANTE ES
 * ---------------------------------
 * El docente está en la sala y tiene que marcar veinte personas sin perder
 * tiempo. Por eso cada fila permite marcar presente con un solo toque.
 *
 * Y debajo de cada marcado aparece el efecto real: si la ausencia consumió una
 * clase del plan o quedó pendiente de decisión. Ocultarlo haría que el docente
 * creyera que descontó cuando el sistema no lo hizo.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

interface FilaAlumno {
  id: string;
  nombre_completo: string;
  cupo_restante: number | null;
}

interface Resultado {
  mensaje: string;
  consume_clase_plan: boolean | null;
  regla_definida: boolean;
  explicacion_regla: string;
}

const ruta = useRoute();
const claseId = ruta.params.id as string;

const clase = ref<{ id: string; nombre: string; inicio: string | null; termina: string | null } | null>(null);
const alumnos = ref<FilaAlumno[]>([]);
const cargando = ref(true);
const error = ref<string | null>(null);

const regla = ref<{ definida: boolean; explicacion: string } | null>(null);

const resultado = ref<Record<string, Resultado>>({});

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    const respuesta = await $fetch<{
      clase: { id: string; nombre: string; inicio: string | null; termina: string | null };
      alumnos: FilaAlumno[];
      regla_ausencia: { definida: boolean; explicacion: string };
    }>(`/api/v1/clases/${claseId}/asistencias`);

    clase.value = respuesta.clase;
    alumnos.value = respuesta.alumnos;
    regla.value = respuesta.regla_ausencia;
  } catch {
    error.value = 'No se pudo cargar la lista de la clase.';
  } finally {
    cargando.value = false;
  }
}

onMounted(cargar);

async function marcar(alumnoId: string, estado: 'presente' | 'ausente'): Promise<void> {
  try {
    const respuesta = await $fetch<{
      mensaje: string;
      asistencia: { consume_clase_plan: boolean | null };
      recuperacion: { id: string; vence_el: string | null; sin_caducidad: boolean } | null;
      regla_ausencia: { definida: boolean; explicacion: string };
      cupo_restante: number | null;
    }>(`/api/v1/clases/${claseId}/alumnos/${alumnoId}/asistencia`, {
      method: 'POST',
      body: {
        estado,
        generar_recuperacion: estado === 'ausente',
      },
    });

    resultado.value = {
      ...resultado.value,
      [alumnoId]: {
        mensaje: respuesta.mensaje,
        consume_clase_plan: respuesta.asistencia.consume_clase_plan,
        regla_definida: respuesta.regla_ausencia.definida,
        explicacion_regla: respuesta.regla_ausencia.explicacion,
      },
    };

    // Se actualiza el cupo en pantalla para que se vea el efecto de inmediato.
    const fila = alumnos.value.find((a) => a.id === alumnoId);
    if (fila) {
      fila.cupo_restante = respuesta.cupo_restante;
    }
  } catch {
    error.value = 'No se pudo registrar la asistencia. Intente de nuevo.';
  }
}

function saldo(alumno: FilaAlumno): string {
  if (alumno.cupo_restante === null) {
    return 'sin plan con cupo definido';
  }

  return `${alumno.cupo_restante} clase(s) disponibles`;
}
</script>

<template>
  <div>
    <h1>Asistencia</h1>

    <p v-if="cargando" class="tenue">Cargando…</p>
    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>

    <template v-if="clase">
      <div class="tarjeta">
        <h2>{{ clase.nombre }}</h2>
      </div>

      <div
        v-if="regla && !regla.definida"
        class="aviso pendiente"
        role="status"
      >
        <p class="titulo-aviso">La regla de consumo por ausencia no está configurada</p>
        <p>{{ regla.explicacion }}</p>
      </div>

      <div v-else-if="regla" class="aviso">
        <p class="titulo-aviso">Regla configurada</p>
        <p>{{ regla.explicacion }}</p>
      </div>

      <div class="tarjeta">
        <p v-if="alumnos.length === 0" class="sin-datos">
          No hay alumnos inscritos en esta clase.
        </p>

        <table v-else>
          <thead>
            <tr>
              <th scope="col">Alumno</th>
              <th scope="col">Saldo del plan</th>
              <th scope="col">Marcar</th>
              <th scope="col">Resultado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="alumno in alumnos" :key="alumno.id">
              <td>{{ alumno.nombre_completo }}</td>
              <td class="chico tenue">{{ saldo(alumno) }}</td>
              <td>
                <div class="fila">
                  <button type="button" @click="marcar(alumno.id, 'presente')">
                    Presente
                  </button>
                  <button type="button" class="peligro" @click="marcar(alumno.id, 'ausente')">
                    Ausente
                  </button>
                </div>
              </td>
              <td class="chico">
                <template v-if="resultado[alumno.id]">
                  <!--
                    El tri-estado se muestra tal cual. Un NULL aquí significa
                    "la regla no está definida", y no se esconde.
                  -->
                  <span v-if="resultado[alumno.id].consume_clase_plan === null" class="aviso pendiente">
                    Pendiente de decidir
                  </span>
                  <span v-else-if="resultado[alumno.id].consume_clase_plan" class="aviso alerta">
                    Consumió la clase
                  </span>
                  <span v-else class="aviso">
                    No consumió la clase
                  </span>
                </template>
                <span v-else class="tenue">Sin marcar</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>