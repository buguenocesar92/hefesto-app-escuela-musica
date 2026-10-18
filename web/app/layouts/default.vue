<script setup lang="ts">
/**
 * Marco de la aplicación: barra superior y navegación.
 *
 * QUÉ APARECE Y QUÉ NO
 * --------------------
 * El menú muestra el tablero, los alumnos, la agenda y las clases siempre, y
 * los pagos SOLO si el rol tiene permiso. Ocultar el enlace no reemplaza al
 * permiso: la comprobación real está en la API, en `PagoPolicy`. Esto es
 * únicamente para que la persona no entre a una pantalla que no le sirve.
 */

const { usuario, salir } = useSesion();

const ruta = useRoute();

const enlaces = computed(() => {
  const base = [
    { ruta: '/', texto: 'Tablero' },
    { ruta: '/alumnos', texto: 'Alumnos' },
    { ruta: '/agenda', texto: 'Agenda' },
    { ruta: '/asistencia', texto: 'Asistencia' },
    { ruta: '/clases', texto: 'Clases' },
    { ruta: '/recuperacion', texto: 'Recuperación' },
    { ruta: '/instrumentos', texto: 'Instrumentos' },
  ];

  if (usuario.value?.administra) {
    base.push({ ruta: '/planes', texto: 'Planes' });
  }

  if (usuario.value?.ve_pagos) {
    base.push({ ruta: '/pagos', texto: 'Pagos' });
  }

  return base;
});

const activo = (destino: string): boolean =>
  destino === '/' ? ruta.path === '/' : ruta.path.startsWith(destino);
</script>

<template>
  <div class="marco">
    <header class="barra">
      <div class="contenido barra-int">
        <NuxtLink to="/" class="marca">
          Escuela de Música y Arte
        </NuxtLink>

        <div v-if="usuario" class="fila">
          <span class="chico tenue">
            {{ usuario.nombre_completo }} · {{ usuario.rol_texto }}
          </span>
          <button class="secundario" type="button" @click="salir()">
            Salir
          </button>
        </div>
      </div>

      <nav v-if="usuario" class="navegacion">
        <div class="contenido nav-int">
          <NuxtLink
            v-for="enlace in enlaces"
            :key="enlace.ruta"
            :to="enlace.ruta"
            class="enlace"
            :class="{ activo: activo(enlace.ruta) }"
          >
            {{ enlace.texto }}
          </NuxtLink>
        </div>
      </nav>
    </header>

    <main class="contenido">
      <slot />
    </main>

    <footer class="pie contenido chico tenue">
      Aplicación interna de la escuela. Los datos de los alumnos no salen de
      estos servidores.
    </footer>
  </div>
</template>

<style scoped>
.marco {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.barra {
  background: var(--superficie);
  border-bottom: 1px solid var(--borde);
  margin-bottom: 1rem;
}

.barra-int {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding-top: 0.75rem;
  padding-bottom: 0.75rem;
  flex-wrap: wrap;
}

.marca {
  font-weight: 700;
  text-decoration: none;
  color: var(--primario);
}

.navegacion {
  border-top: 1px solid var(--borde);
}

.nav-int {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
}

.enlace {
  padding: 0.5rem 0.75rem;
  text-decoration: none;
  color: var(--texto-suave);
  border-bottom: 3px solid transparent;
}

.enlace:hover {
  color: var(--texto);
}

/* El borde, no solo el color: distingue la sección actual sin depender del
   color. */
.enlace.activo {
  color: var(--primario);
  font-weight: 600;
  border-bottom-color: var(--primario);
}

.pie {
  margin-top: auto;
  padding: 1rem;
  border-top: 1px solid var(--borde);
}
</style>