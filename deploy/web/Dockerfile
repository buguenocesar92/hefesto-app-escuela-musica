# ---------------------------------------------------------------------------
# Imagen de la interfaz (Nuxt con renderizado en el servidor)
# ---------------------------------------------------------------------------
# Dos etapas: compilación y ejecución. La etapa de ejecución NO tiene las
# herramientas de compilación, así que la imagen final es mucho menor y la
# superficie de ataque también.
#
# Referencia de versiones:
#   node:24.22.0-alpine3.24
#
# PENDIENTE ANTES DE PRODUCCIÓN
# -----------------------------
# Fijar por huella digital (digest) SHA256 la imagen base. Ver docs/10.
#
# ADVERTENCIA HONESTA: esta imagen NO se construyó. En el entorno donde se
# escribió el código no había Docker ni Node. Ver docs/10-PRUEBAS-Y-ESTADO.md.
# ---------------------------------------------------------------------------

# ---------------------------------------------------------------------------
# Etapa 1: compilar.
# ---------------------------------------------------------------------------
FROM node:24.22.0-alpine3.24 AS construccion

WORKDIR /app

# Se copian primero los manifiestos para que la capa de dependencias se
# reutilice cuando no cambian las versiones.
COPY package.json package-lock.json* ./

# `--ignore-scripts` porque `postinstall` corre `nuxt prepare`, que necesita el
# código fuente y todavía no está copiado.
RUN npm ci --ignore-scripts

COPY . .

ENV NUXT_TELEMETRY_DISABLED=1
RUN npm run build

# ---------------------------------------------------------------------------
# Etapa 2: ejecutar.
# ---------------------------------------------------------------------------
FROM node:24.22.0-alpine3.24 AS app

WORKDIR /app

ENV NODE_ENV=production
ENV NUXT_TELEMETRY_DISABLED=1
ENV NITRO_HOST=0.0.0.0
ENV NITRO_PORT=3000

COPY --from=construccion /app/.output ./.output

# El proceso no corre como root.
USER node

EXPOSE 3000

CMD ["node", ".output/server/index.mjs"]