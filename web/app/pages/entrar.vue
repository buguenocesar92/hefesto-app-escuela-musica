<script setup lang="ts">
/**
 * Pantalla de entrada.
 *
 * QUÉ HACE ESTA PANTALLA Y QUÉ NO
 * -------------------------------
 * No valida contraseñas y no las pide: el ingreso lo resuelve Authentik. Esta
 * pantalla elige la ESCUELA y después entrega el control al proveedor.
 *
 * El listado de escuelas lo entrega `GET /api/v1/auth/escuelas`, que
 * lee la vista `vw_escuelas_publicas`. Esa vista expone solo `slug`, `nombre` y
 * `activo`, sin razón social ni RUT: son datos de la escuela que nadie debería
 * poder enumerar desde un formulario abierto.
 */

definePageMeta({
  layout: 'default',
});

import type { EscuelaPublica } from '~/tipos/api';

const ruta = useRoute();

const { usuario } = useSesion();

const escuelas = ref<EscuelaPublica[]>([]);
const seleccionada = ref('');
const cargando = ref(true);
const enviando = ref(false);
const mensaje = ref<string | null>(null);

onMounted(async () => {
  try {
    const respuesta = await $fetch<{ escuelas: EscuelaPublica[] }>(
      '/api/v1/auth/escuelas'
    );

    escuelas.value = respuesta.escuelas;
  } catch {
    mensaje.value =
      'No se pudo consultar la lista de escuelas. Revise que la aplicación esté funcionando.';
  } finally {
    cargando.value = false;
  }
});

// Si ya hay sesión, no tiene sentido mostrar el ingreso.
watchEffect(() => {
  if (usuario.value) {
    navigateTo('/');
  }
});

/**
 * Entrega el control al proveedor de identidad.
 *
 * La API devuelve la dirección del proveedor en un JSON, en vez de redirigir
 * ella misma. Así la redirección la hace el navegador y no depende de que una
 * cabecera de redirección atraviese el proxy.
 */
async function entrar(): Promise<void> {
  if (!seleccionada.value) {
    mensaje.value = 'Elija una escuela para continuar.';
    return;
  }

  mensaje.value = null;
  enviando.value = true;

  try {
    const destino = typeof ruta.query.redirigir_a === 'string'
      ? ruta.query.redirigir_a
      : '/';

    const respuesta = await $fetch<{ url_autorizacion: string }>(
      '/api/v1/auth/login',
      {
        query: { escuela: seleccionada.value, redirigir_a: destino },
      }
    );

    // `external` porque la dirección es del proveedor, no de esta aplicación.
    await navigateTo(respuesta.url_autorizacion, { external: true });
  } catch {
    mensaje.value = 'No se pudo iniciar el ingreso. Intente de nuevo.';
    enviando.value = false;
  }
}

const mensajeDeError = computed<string | null>(() => {
  const parametro = ruta.query.mensaje;

  return typeof parametro === 'string' ? parametro : mensaje.value;
});
</script>

<template>
  <div class="entrada">
    <div class="tarjeta caja">
      <h1>Escuela de Música y Arte</h1>
      <p class="tenue chico">
        Elija la escuela a la que pertenece su cuenta. El inicio de sesión se
        completa en el proveedor de identidad de la escuela.
      </p>

      <div v-if="mensajeDeError" class="aviso alerta" role="alert">
        <p class="titulo-aviso">No se pudo continuar</p>
        <p>{{ mensajeDeError }}</p>
      </div>

      <p v-if="cargando" class="tenue">Cargando escuelas…</p>

      <p v-else-if="escuelas.length === 0" class="aviso pendiente">
        No hay ninguna escuela activa. Si cree que es un error, pida a la
        dirección que la habilite.
      </p>

      <form v-else @submit.prevent="entrar">
        <label for="escuela">Escuela</label>
        <select id="escuela" v-model="seleccionada" required>
          <option value="" disabled>Seleccione una opción</option>
          <option v-for="item in escuelas" :key="item.slug" :value="item.slug">
            {{ item.nombre }}
          </option>
        </select>

        <button type="submit" class="continuar" :disabled="enviando">
          {{ enviando ? 'Entrando…' : 'Continuar' }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.entrada {
  display: flex;
  justify-content: center;
  padding: 2rem 1rem;
}

.caja {
  width: 100%;
  max-width: 28rem;
}

.continuar {
  margin-top: 1rem;
  width: 100%;
}
</style>