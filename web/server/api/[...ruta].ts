import {
  appendResponseHeader,
  defineEventHandler,
  getCookie,
  getMethod,
  getRequestHeader,
  getRequestURL,
  sendRedirect,
  setResponseStatus,
} from 'h3';

/**
 * BFF: reenvía `/api/**` a la API de Laravel.
 *
 * QUÉ HACE Y POR QUÉ
 * ------------------
 * 1. Copia las cookies de la petición del navegador hacia la API. La cookie de
 *    sesión es HttpOnly: el navegador no la puede leer, pero sí la puede
 *    reenviar, que es exactamente lo que hace este archivo.
 *
 * 2. Copia la cookie `XSRF-TOKEN` como cabecera `X-XSRF-TOKEN`. Laravel acepta
 *    esa cabecera para validar el token CSRF. Sin esto, toda escritura desde el
 *    navegador sería rechazada.
 *
 * 3. Copia las cookies de la respuesta de vuelta, que es lo que permite que la
 *    sesión se abra y se renueve.
 *
 * 4. Convierte la respuesta del callback de Authentik en una navegación. Ese
 *    endpoint devuelve JSON, pero en el navegador tiene que ser un cambio de
 *    página, no un JSON.
 *
 * POR QUÉ EXISTE ESTA CAPA Y NO UN LLAMADO DIRECTO A LA API
 * -------------------------------------------------------
 * La sesión vive en una cookie HttpOnly con SameSite. Si el navegador llamara
 * a la API por su cuenta, esa cookie no viajaría y habría que resolver el
 * problema con permisos entre orígenes y credenciales, que además duplica el
 * riesgo de falsificación de peticiones.
 *
 * Por lo tanto: el navegador nunca ve la dirección interna de la API, y nunca
 * le habla directo.
 */
export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event);
  const url = getRequestURL(event);
  const metodo = getMethod(event).toUpperCase();

  // `/api/v1/alumnos` -> `v1/alumnos`
  const ruta = url.pathname.replace(/^\/api\/?/, '');

  const destino = new URL(`${String(config.apiBase).replace(/\/+$/, '')}/${ruta}`);
  destino.search = url.search;

  const cabeceras: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };

  const cookies = getRequestHeader(event, 'cookie');

  if (cookies) {
    cabeceras.cookie = cookies;
  }

  const tokenCsrf = getCookie(event, 'XSRF-TOKEN');

  if (tokenCsrf) {
    // Laravel guarda el valor con la cookie codificada en la URL.
    cabeceras['X-XSRF-TOKEN'] = decodeURIComponent(tokenCsrf);
  }

  let respuesta: Response;

  try {
    respuesta = await fetch(destino.toString(), {
      method: metodo,
      headers: cabeceras,
      // El cuerpo solo existe en los métodos que lo tienen. Reenviarlo tal cual
      // es lo que evita tener que entender el formato acá: la API sigue siendo
      // la que valida.
      body: metodo === 'GET' || metodo === 'HEAD' ? undefined : await cuerpoDe(event),
      redirect: 'manual',
    });
  } catch (error) {
    setResponseStatus(event, 502);

    return {
      mensaje: 'No se pudo contactar a la API de la escuela. Intente en unos segundos.',
      detalle: error instanceof Error ? error.message : String(error),
    };
  }

  const esCallbackDeAuthentik = ruta === 'v1/auth/callback' && metodo === 'GET';

  if (esCallbackDeAuthentik) {
    return manejarCallbackDeAuthentik(event, respuesta);
  }

  propagarCookiesDeRespuesta(event, respuesta);

  setResponseStatus(event, respuesta.status);
  appendResponseHeader(
    event,
    'content-type',
    respuesta.headers.get('content-type') ?? 'application/json',
  );

  // La sesión no debe quedar en cachés intermedias: el contenido depende de
  // quién esté conectado, y estos datos son de menores de edad.
  appendResponseHeader(event, 'cache-control', 'private, no-store');

  return await respuesta.text();
});

/**
 * El callback de Authentik se responde con una navegación, no con un JSON.
 *
 * La API devuelve el destino dentro del cuerpo porque para ella es una petición
 * de servidor. Para el navegador tiene que ser un cambio de página.
 */
async function manejarCallbackDeAuthentik(
  event: Parameters<typeof sendRedirect>[0],
  respuesta: Response,
) {
  propagarCookiesDeRespuesta(event, respuesta);

  const cuerpo = await respuesta.text();

  if (!respuesta.ok) {
    let mensaje = 'No se pudo iniciar sesión.';

    try {
      const datos = JSON.parse(cuerpo) as { mensaje?: string };
      mensaje = datos.mensaje ?? mensaje;
    } catch {
      // Se deja el mensaje genérico: la respuesta de la API no es JSON.
    }

    return sendRedirect(event, `/entrar?mensaje=${encodeURIComponent(mensaje)}`, 302);
  }

  let destino = '/';

  try {
    const datos = JSON.parse(cuerpo) as { redirigir_a?: string };
    destino = destinoSeguro(datos.redirigir_a);
  } catch {
    // Cuerpo ilegible: se vuelve al inicio, que exige sesión y, si no la hay,
    // devuelve a la pantalla de entrada.
  }

  return sendRedirect(event, destino, 302);
}

/**
 * Reenvía las cookies que la API haya puesto o cambiado.
 *
 * Se usa `getSetCookie()` cuando existe, porque varias cookies no se pueden
 * juntar en una sola cabecera `set-cookie`.
 */
function propagarCookiesDeRespuesta(
  event: Parameters<typeof appendResponseHeader>[0],
  respuesta: Response,
) {
  const cabeceras = respuesta.headers as Headers & { getSetCookie?: () => string[] };

  const valores = typeof cabeceras.getSetCookie === 'function'
    ? cabeceras.getSetCookie()
    : [respuesta.headers.get('set-cookie')].filter((valor): valor is string => Boolean(valor));

  for (const cookie of valores) {
    appendResponseHeader(event, 'set-cookie', cookie);
  }
}

/**
 * Lee el cuerpo de la petición como texto.
 *
 * Se reenvía el texto tal cual, sin interpretarlo: la API sigue siendo la que
 * decide si los datos son válidos.
 */
async function cuerpoDe(event: Parameters<typeof getMethod>[0]): Promise<string | undefined> {
  const bruto = await event.req.text();

  return bruto.length > 0 ? bruto : undefined;
}

/**
 * Solo se aceptan rutas internas como destino.
 *
 * Una dirección completa, o algo que empiece con `//`, convertiría el ingreso
 * en una redirección abierta: el atacante manda a la víctima a un sitio falso
 * justo después de iniciar sesión, que es el peor momento posible.
 *
 * La API también lo valida (ver `AuthController::destinoSeguro`). Se repite acá
 * porque el BFF es el primero que recibe la respuesta y decide a dónde llevar al
 * navegador. Las dos capas no se estorban.
 */
function destinoSeguro(candidato: unknown): string {
  if (typeof candidato !== 'string' || candidato === '') {
    return '/';
  }

  if (! candidato.startsWith('/')) {
    return '/';
  }

  if (candidato.startsWith('//') || candidato.startsWith('/\\')) {
    return '/';
  }

  return candidato;
}