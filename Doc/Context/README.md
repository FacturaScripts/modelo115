# Modelo115: contexto del equipo

Esta carpeta guarda **por qué el código es como es**: las decisiones que no se ven leyendo una sola clase, para que
todo el equipo y los agentes trabajen con el mismo contexto. Lee este índice y abre solo el tema o la decisión que
necesites.

Se puede dividir en tantos archivos y subcarpetas como haga falta; este índice dice qué hay en cada uno y se actualiza
en el mismo trabajo que cambia el código.

## Temas

- [`01-arquitectura.md`](01-arquitectura.md): piezas del plugin, estructura por modelo y por qué hay traits.
- [`02-modelo-de-datos.md`](02-modelo-de-datos.md): tabla de inmuebles y columna `idinmueble180` en las compras.
- [`03-calculo.md`](03-calculo.md): filtro IRPFA, casillas, reparto por inmueble y reinicio entre llamadas.
- [`04-ficheros-aeat.md`](04-ficheros-aeat.md): ficheros `.115` (DR115) y 180 (500 posiciones, ISO-8859-1).
- [`05-api.md`](05-api.md): endpoints de solo lectura, errores y permisos.
- [`06-evolucion.md`](06-evolucion.md): etapas del código según el historial.

## Normativa

- [`Normativa/`](Normativa/): documentación **oficial** de los modelos 115 y 180 (AEAT y BOE), descargada con la skill
  `download-modelo115` (`.agents/skills/`). Contiene `actual/` (diseños de registro, especificaciones y normativa del
  BOE en `actual/normativa/`), `paginas/` (texto de la ayuda técnica, gestiones y normativa de la Sede),
  `historico/` (versiones sustituidas), `manifest.json` y [`CHANGELOG.md`](Normativa/CHANGELOG.md) (qué se bajó, versiones
  vigentes, alertas y lo que queda por descargar a mano). Contrasta con ella cualquier decisión sobre claves, campos o
  plazos; para refrescarla, ejecuta la skill.

## Decisiones

- [`0001-irpfa-separa-115-de-111.md`](Decisiones/0001-irpfa-separa-115-de-111.md): solo las retenciones IRPFA van al 115.
- [`0002-reparto-por-porcentaje-entre-inmuebles.md`](Decisiones/0002-reparto-por-porcentaje-entre-inmuebles.md): facturas sin inmueble.
- [`0003-traits-para-no-desplegar-en-dinamic.md`](Decisiones/0003-traits-para-no-desplegar-en-dinamic.md): traits en lugar de clase base.
- [`0004-api-de-solo-lectura.md`](Decisiones/0004-api-de-solo-lectura.md): la API solo admite GET.
