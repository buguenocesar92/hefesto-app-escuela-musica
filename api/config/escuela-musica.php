<?php

declare(strict_types=1);

return [

    /*
    |-----------------------------------------------------------------------
    | Reglas de negocio que la escuela decide
    |-----------------------------------------------------------------------
    |
    | Este bloque está lleno de valores que NO tienen un valor por omisión
    | razonable, y esa es la idea. Tres decisiones de la escuela no se pueden
    | deducir de nada:
    |
    |   1. Si una ausencia consume la clase del plan mensual o no.
    |   2. Cuántos días vive una clase recuperada antes de caducar.
    |   3. Cuántos días de margen se avisa de que un plan está por vencer.
    |
    | Las tres quedan en `null` cuando no están configuradas. El sistema NO las
    | deduce ni las rellena con un número que parezca sensato. Un campo en
    | `null` significa "esto todavía no lo decidió la escuela", y el sistema
    | lo trata así:
    |
    |   - La ausencia se registra igual, pero queda marcada como pendiente de
    |     definir. No se consume nada y no se inventa el sentido de la regla.
    |   - La recuperación no caduca, y la deuda de clases queda a la vista en
    |     el tablero y en `escuela-musica:vencer-recuperaciones`.
    |
    | Ver docs/05-CONFIGURACION.md y docs/10-PRUEBAS-Y-ESTADO.md.
    |
    */

    /*
    | ¿Una ausencia consume la clase del plan?
    |
    | Valores aceptados: 'si' o 'no'. Cualquier otro valor, o vacío, se trata
    | como "sin definir". No hay valor por omisión porque las dos respuestas son
    | defendibles y cambian el ingreso: si consume, la escuela pierde la clase; si
    | no consume, el alumno la conserva y puede recuperarla.
    */
    'ausencia_consume_plan' => env('ESCUELA_MUSICA_ABSENCIA_CONSUME_PLAN'),

    /*
    | ¿Cuántos días tiene de vida una clase recuperada?
    |
    | `null` = sin caducidad. La deuda se acumula y por eso se muestra aparte en
    | el tablero: una deuda invisible no es una deuda controlada. Ver la
    | sección "Deuda de clases" en docs/02-MODELO-DE-DATOS.md.
    */
    'dias_vigencia_recuperacion' => env('ESCUELA_MUSICA_DIAS_RECUPERACION') === null
        || env('ESCUELA_MUSICA_DIAS_RECUPERACION') === ''
            ? null
            : (int) env('ESCUELA_MUSICA_DIAS_RECUPERACION'),

    /*
    | ¿Con cuántos días de anticipación se avisa de un plan por vencer?
    |
    | `null` = no se avisa. La lista de morosos se sigue viendo completa, sin
    | este filtro. Ver docs/09-API.md.
    */
    'dias_aviso_morosidad' => env('ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD') === null
        || env('ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD') === ''
            ? null
            : (int) env('ESCUELA_MUSICA_DIAS_AVISO_MOROSIDAD'),

    /*
    |-----------------------------------------------------------------------
    | Qué roles pueden ver los pagos
    |-----------------------------------------------------------------------
    |
    | Un docente necesita ver quién viene a su clase, no cuánto pagó la familia.
    | Por eso esto es una lista explícita y no "todos los que pueden entrar".
    | El rol vive en `usuarios.rol` como texto, y se contrasta contra esta lista.
    */
    'roles_que_ven_pagos' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ESCUELA_MUSICA_ROLES_QUE_VEN_PAGOS', 'direccion,administracion'))
    ))),

    /*
    |-----------------------------------------------------------------------
    | Roles que administran
    |-----------------------------------------------------------------------
    |
    | Crear y editar alumnos, planes, salas y clases es tarea de la
    | administración de la escuela, no de cualquiera que tenga sesión abierta.
    */
    'roles_que_administran' => ['direccion', 'administracion'],

    /*
    | Roles que registran asistencia. El docente que imparte la clase va aquí:
    | es el que está en la sala cuando ocurre.
    */
    'roles_que_registran_asistencia' => ['direccion', 'administracion', 'docente', 'recepcion'],

    /*
    |-----------------------------------------------------------------------
    | Retención de la bitácora de accesos
    |-----------------------------------------------------------------------
    |
    | Es un valor por omisión, pero NO es una decisión de la ley ni del cliente:
    | es el mismo valor por omisión que usan las demás aplicaciones de Hefesto,
    | para que el recorte no se ejecute el primer día y borre medio año de
    | historial de un tirón. Ver docs/06-SEGURIDAD-Y-LEY-19628.md.
    */
    'retencion_bitacora_horas' => (int) env('ESCUELA_MUSICA_RETENCION_BITACORA_HORAS', 8760),

    /*
    |-----------------------------------------------------------------------
    | Formato de la fecha de vencimiento de una recuperación
    |-----------------------------------------------------------------------
    |
    | Cuando `dias_vigencia_recuperacion` está vacío no se usa esta cadena: no
    | hay fecha que formatear.
    */
    'formato_fecha' => 'd/m/Y',

    /*
    | Tamaño máximo de página en los listados. Es un techo, no un valor fijo:
    | un listado nunca devuelve más de esta cantidad de filas por consulta.
    */
    'por_pagina_maximo' => (int) env('ESCUELA_MUSICA_POR_PAGINA_MAXIMO', 100),

    /*
    |-----------------------------------------------------------------------
    | Autorización del adulto responsable
    |-----------------------------------------------------------------------
    |
    | `true` obliga a que exista un responsable con autorización registrada antes
    | de permitir dar de alta una clase a un alumno menor de edad. El valor por
    | omisión es `true` porque se trata de datos de un menor: es la postura
    | segura. La escuela puede bajarlo a `false` si su responsable legal decide
    | otra cosa, y queda constancia en el registro de la autorización.
    */
    'exigir_autorizacion_responsable' => (bool) env('ESCUELA_MUSICA_EXIGIR_AUTORIZACION', true),

    /*
    |-----------------------------------------------------------------------
    | Zona horaria
    |-----------------------------------------------------------------------
    |
    | La agenda se guarda en hora de verano o de invierno chilena según la
    | fecha. Si se dejara una zona fija sin cambio de horario, en winter una
    | clase de las 18:00 se desplazaría una hora al mostrarla.
    */
    'zona_horaria' => env('APP_TIMEZONE', 'America/Santiago'),

];