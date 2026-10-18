<script setup lang="ts">
/**
 * Pagos y estado de cuenta.
 *
 * LO QUE ESTÁ DE ADORNOS AQUÍ
 * ---------------------------
 * Nada. Un total sin desglose no sirve para llamar a una familia: hay que poder
 * ver qué periods están pendientes.
 */

definePageMeta({
  middleware: 'auth',
  layout: 'default',
});

import type { Pago } from '~/tipos/api';

interface EstadoAlumno {
  alumno: { id: string; nombre_completo: string };
  pagos: Pago[];
  saldo_pendiente: number;
  tiene_montos_sin_definir: boolean;
}

interface Resumen {
  pagos: Array<{
    id: string;
    alumno: string | null;
    concepto: string;
    monto: number | null;
    periodo_hasta: string | null;
    pagado_en: string | null;
  }>;
  meta: { total: number; paginas: number };
}

const soloPendientes = ref(true);
const pagina = ref(1);

const resumen = ref<Resumen | null>(null);
const cuenta = ref<EstadoAlumno | null>(null);

const cargando = ref(true);
const error = ref<string | null>(null);

async function cargar(): Promise<void> {
  cargando.value = true;
  error.value = null;

  try {
    resumen.value = await $fetch<Resumen>('/api/v1/pagos', {
      query: { solo_pendientes: soloPendientes.value },
    });
  } catch {
    // Un 404 aquí significa que el rol no tiene permiso para ver pagos. Se dice
    // con palabras, sin código HTTP: el texto de la API no se filtra.
    error.value = 'No tiene permiso para ver los pagos, o no se pudieron cargar.';
  } finally {
    cargando.value = false;
  }
}

async function verCuenta(alumnoId: string): Promise<void> {
  try {
    cuenta.value = await $fetch<EstadoAlumno>(`/api/v1/alumnos/${alumnoId}/pagos`);
  } catch {
    cuenta.value = null;
  }
}

onMounted(cargar);
watch(soloPendientes, cargar);

/** Un cargo sin monto definido se muestra con palabras, no con un cero. */
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
    <h1>Pagos</h1>

    <div class="tarjeta">
      <div class="fila">
        <input id="pendientes" v-model="soloPendientes" type="checkbox" />
        <label for="pendientes">Mostrar solo los pendientes</label>
      </div>
    </div>

    <p v-if="error" class="aviso alerta" role="alert">{{ error }}</p>
    <p v-if="cargando" class="tenue">Cargando…</p>

    <template v-else-if="resumen">
      <div class="tarjeta">
        <p class="chico tenue">
          {{ resumen.meta.total }} registro(s).
        </p>

        <p v-if="resumen.pagos.length === 0" class="sin-datos">
          No hay pagos que coincidan con el filtro.
        </p>

        <table v-else>
          <thead>
            <tr>
              <th scope="col">Alumno</th>
              <th scope="col">Concepto</th>
              <th scope="col">Monto</th>
              <th scope="col">Hasta</th>
              <th scope="col">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pago in resumen.pagos" :key="pago.id">
              <td>{{ pago.alumno ?? '—' }}</td>
              <td>{{ pago.concepto === 'cuota_plan' ? 'Cuota de plan' : 'Clase suelta' }}</td>
              <td>{{ monto(pago.monto) }}</td>
              <td>{{ pago.periodo_hasta ?? '—' }}</td>
              <td>
                <span v-if="pago.pagado_en">Pagado</span>
                <span v-else class="aviso pendiente chico">Pendiente</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>