/**
 * Configuracion de Nuxt.
 *
 * ESTA APLICACION NO ES UN SITIO PUBLICO
 * -------------------------------------
 * La usa el personal de una escuela: dirección, administración, docentes y
 * recepción. No hay indexación pública ni páginas compartibles por enlace, y por
 * eso el robot de buscadores queda prohibido de forma explícita.
 *
 * El proxy del API
 * ----------------
 * `proxy` reenvía `/api/**` al servidor de la API. La razón es que la sesión
 * vive en una cookie que el navegador NO debe mandar directamente a la API:
 * sería una cookie de sesión enviada a otro origen, y con ella podría viajar a un
 * sitio que la aplicación no controla. Con el proxy, el navegador habla siempre
 * con el mismo origen (el de Nuxt) y es el servidor de Nuxt el que habla con la
 * API.
 */

export default defineNuxtConfig({
  compatibilityDate: '2026-01-01',

  devtools: { enabled: false },

  app: {
    head: {
      htmlAttrs: { lang: 'es-CL' },
      title: 'Escuela de Música y Arte',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        // Es una aplicación interna: no se indexa y no se guardan rastreadores.
        { name: 'robots', content: 'noindex, nofollow, noarchive' },
        { name: 'referrer', content: 'same-origin' },
      ],
      link: [{ rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' }],
    },
  },

  runtimeConfig: {
    // Configuración PRIVADA: solo la usa el servidor de Nuxt. Nunca se expone al
    // navegador, porque revela la direccion interna de la API.
    apiBase: process.env.NUXT_API_BASE ?? 'http://localhost:8080',

    // Configuracion PUBLICA: es el origen por el que entra la gente.
    public: {
      basePublico: process.env.NUXT_PUBLIC_BASE ?? 'http://localhost:3000',
    },
  },

  css: ['~/assets/css/main.css'],

  routeRules: {
    // Las pantallas con sesión no deben quedar en la caché compartida del proxy.
    '/**': { headers: { 'cache-control': 'no-store' } },
  },

  nitro: {
    routeRules: {
      '/**': { headers: { 'x-content-type-options': 'nosniff' } },
    },
  },
});