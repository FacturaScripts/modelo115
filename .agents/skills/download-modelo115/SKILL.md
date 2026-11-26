---
name: download-modelo115
description: Descarga toda la documentación oficial actualizada de los modelos 115 y 180 de la AEAT (ayuda técnica, gestiones, diseños de registro, especificaciones de desarrolladores y normativa del BOE) en Doc/Context/Normativa
---

Eres un asistente que mantiene actualizada la documentación OFICIAL de los modelos 115 (autoliquidación mensual/trimestral de retenciones e ingresos a cuenta sobre rentas procedentes del arrendamiento o subarrendamiento de inmuebles urbanos) y 180 (resumen anual de esas retenciones) de la Agencia Tributaria, tanto la técnica (diseños de registro, especificaciones de presentación) como la de usuario (ayuda de presentación, plazos, novedades) y la normativa (BOE).

DOMINIOS PERMITIDOS: sede.agenciatributaria.gob.es, www2.agenciatributaria.gob.es, www.agenciatributaria.es (solo /static_files/AEAT_Desarrolladores/ y el portal de desarrolladores), www.boe.es. Nada de webs de terceros, foros, blogs ni GitHub, aunque aparezcan en buscadores. Los formularios y servicios con identificación (www1.agenciatributaria.gob.es/wlpl/..., TGVI online, Cl@ve, certificados) NO se visitan ni se usan.

DESCARGA: usa SIEMPRE /usr/bin/curl (SecureTransport de macOS) con -L y un User-Agent de navegador. NUNCA uses -k/--insecure. Existe un script ya probado que implementa esta tarea; úsalo y revisa su salida:
  python3 -I /Users/daniel89fg/Trabajos/FacturaScripts/facturascripts/Plugins/Modelo115/.agents/skills/download-modelo115/scripts/sincronizar.py /Users/daniel89fg/Trabajos/FacturaScripts/facturascripts/Plugins/Modelo115/Doc/Context/Normativa
Su salida y Doc/Context/Normativa/.log_run.json incluyen: nuevos, modificados, iguales, errores, páginas nuevas/cambiadas/desaparecidas y los enlaces descubiertos.

PÁGINAS (guardar texto en paginas/, con la URL de origen en la primera línea). El script RASTREA RECURSIVAMENTE las páginas .html de estas secciones a partir de las semillas, así que las páginas nuevas se detectan solas:
- Modelo 115:
  - Ayuda técnica: /Sede/ayuda/consultas-informaticas/presentacion-declaraciones-ayuda-tecnica/modelo-115* (presentación electrónica, con datos anteriores, en papel).
  - Gestiones y obligaciones del retenedor: /Sede/irpf/retenciones-ingresos-cuenta-pagos-fraccionados/retenciones-ingresos-cuenta/modelo-115.html y `.../obligaciones-retenedor/` (modelo-115, gestiones, obligaciones).
  - Ficha del procedimiento: /Sede/procedimientos/GH02.shtml (solo se guarda).
  - Guía práctica del censal 036, capítulo 9 (retenciones), solo las páginas del 115 y las generales.
  - Folleto de actividades económicas, 7.3 «Retenciones por arrendamiento de bienes inmuebles. Modelos 115 y 180», y el manual de sociedades (retenciones por arrendamientos de inmuebles urbanos).
  - No existe página de «instrucciones» del 115 en /Sede/todas-gestiones/.../pagos-cuenta (sí la del 111): si la Sede la publica, el prefijo `pagos-cuenta/modelo-115-` ya está en CRAWL_PREFIXES.
- Modelo 180:
  - Normativa de la campaña de informativas: /Sede/declaraciones-informativas-otros-impuestos-tasas/campana-declaraciones-informativas-2025 (normativa del 180, novedades de la campaña por modelo, plazos, cuestiones relevantes, notas informativas, proyectos de orden). Se descartan las páginas de otros modelos y las charlas informativas.
  - Ayuda técnica: /Sede/ayuda/consultas-informaticas/declaraciones-informativas-ayuda-tecnica/modelos-038-180/modelo-180-* (formulario, fichero y cómo emitir certificados), consulta y baja de declaraciones.
  - Gestiones y ejercicios anteriores: /Sede/todas-gestiones/impuestos-tasas/declaraciones-informativas/modelo-180-*
- Diseños de registro: /Sede/ayuda/disenos-registro/modelos-100-199.html (solo se descargan los ficheros del 115 y del 180).
- NO se descargan los cuadros de tipos de retención, el algoritmo del cálculo de retenciones ni el certificado único (EXCLUIR_ENLACES): son de rendimientos del trabajo (modelo 111) y no aplican a alquileres, cuyo tipo es fijo (19 %).
- Si se amplía el rastreo, cambia CRAWL_PREFIXES / PAGES / EXCLUIR_PAGINAS en sincronizar.py.
- Las páginas guardadas antes que ya no aparecen se listan en "paginas_desaparecidas" (anótalas en el CHANGELOG; no se borran).

FICHEROS DIRECTOS (a actual/):
- Diseño de registro del 115 (Orden EHA/3435/2007, ejercicios 2019 y siguientes, v 1.3): https://sede.agenciatributaria.gob.es/static_files/Sede/Disenyo_registro/DR_100_199/archivos/DR115e15v13.xls
- Diseño de registro del 180 (Orden HAP/1732/2014 actualizada por la Orden HFP/1284/2023): https://sede.agenciatributaria.gob.es/static_files/Sede/Disenyo_registro/DR_100_199/archivos_23/DR_Mod_180_2023.pdf
- Portal de desarrolladores (base https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/):
  Modelo_115/espes115e22v10.docx (servicios de ayuda a la presentación del 115, ejercicios 2022 y siguientes)
  Modelo_115/espes115e19v11.docx (ídem, ejercicios 2019 a 2021)
  Informativas/General/Especificaciones_TGVI_Online.pdf (validación y presentación por Internet de las informativas con TGVI Online; sirve al 180)
  General/EspecificacionesServiciosDeclaracionesAEAT_ES.pdf (servicios comunes de declaraciones: presentación directa, validación e impresión, consulta)
  General/consultas.xsd (esquema del servicio de consulta de declaraciones presentadas)
  Los tres últimos son los mismos que usa el plugin Modelo111 (`../Modelo111/Doc/Context/Normativa/actual/`): si el SHA-256 publicado coincide, se pueden copiar de allí con `registrar_descarga.py`.
- Cualquier otro fichero (.pdf .zip .xls .xlsx .xsd .xml .doc .docx .txt) enlazado desde las páginas anteriores, salvo los de otros modelos (EXCLUIR_ENLACES): `NOVEDADES_2025.pdf` y `RECORDATORIOS_INF_2025.pdf` de la campaña de informativas, etc.

NORMATIVA (guardar el texto consolidado en actual/normativa/, desde https://www.boe.es/buscar/act.php?id=BOE-A-...): las órdenes que la propia Sede enlaza como normativa de los dos modelos:
- 115 y 180: Orden de 20 de noviembre de 2000 (BOE-A-2000-21430), que aprueba ambos modelos; Orden HAC/540/2003 (BOE-A-2003-5305, colaboración social en la presentación del 115 y del 180); Orden EHA/3435/2007 (BOE-A-2007-20485, diseño de registro del 115) y su modificación HAC/1417/2018 (BOE-A-2018-17997).
- Formas de presentación y diseños del 180: EHA/3062/2010 (BOE-A-2010-18368), HAP/2725/2012 (BOE-A-2012-15380), HAP/2194/2013 (BOE-A-2013-12385, procedimientos de presentación), HAP/1732/2014 (BOE-A-2014-9740), HAC/1276/2020 (BOE-A-2020-17271), HFP/1351/2021 (BOE-A-2021-20004) y HFP/1284/2023 (BOE-A-2023-24412).
- Base legal: Ley 35/2006 del IRPF (BOE-A-2006-20764, art. 99 y siguientes), Reglamento del IRPF, RD 439/2007 (BOE-A-2007-6820, art. 74 y siguientes), Ley 27/2014 del Impuesto sobre Sociedades (BOE-A-2014-12328), su Reglamento, RD 634/2015 (BOE-A-2015-7771) y el texto refundido de la Ley del IRNR, RDLeg 5/2004 (BOE-A-2004-4527, establecimientos permanentes).
- En cada ejecución, mira en las páginas de normativa de la Sede (`normativa/modelo-180`, la ficha GH02 y las notas de la campaña) si aparece una orden nueva que no esté en la lista (p. ej. un proyecto de orden que se publique en el BOE) y añádela a DIRECT en sincronizar.py. Anota en el CHANGELOG si el texto consolidado muestra nuevas modificaciones.

REGLAS COMUNES
1. Fuentes: SOLO los dominios oficiales listados en esta tarea.
2. Estructura dentro de la carpeta Doc/Context/Normativa del plugin:
   - actual/            → última versión de cada fichero (nombre original conservado)
   - actual/normativa/  → textos consolidados del BOE
   - Nombres: ningún nombre de archivo pasa de 60 caracteres (el script los acorta con `nombre_corto()` de `../FiscalES/.agents/skills/download-fiscales/scripts/nombres.py`, que añade una huella del nombre completo; el oficial queda en la URL del manifest), para que el plugin se pueda descargar en Windows. Si el script avisa de un `NOMBRE LARGO`, corrígelo antes de terminar.
   - historico/AAAA-MM-DD/ → versiones anteriores sustituidas (mover aquí, nunca borrar)
   - paginas/           → copia en texto/Markdown de cada página (primera línea: URL de origen) para detectar cambios
   - manifest.json      → por fichero: url, ruta, sha256, tamaño, fechas de descarga y de comprobación, cabeceras ETag/Last-Modified
   - CHANGELOG.md       → añade una entrada con fecha por cada ejecución
3. Procedimiento en cada ejecución:
   a) Descarga cada URL de la lista. Calcula SHA-256 y compáralo con manifest.json.
   b) Si el hash cambió: mueve la versión vieja a historico/AAAA-MM-DD/ y guarda la nueva en actual/.
   c) En cada página, extrae TODOS los enlaces a documentos y descarga los que no estén en el manifest (así detectas documentos nuevos con nombre distinto).
   d) Anota en el CHANGELOG: ficheros nuevos, modificados (con versión/fecha que figure dentro del documento), desaparecidos (404) y páginas cuyo texto cambió. Si una página muestra "Página actualizada: fecha", guárdala.
4. Seguridad: no ejecutes ni abras macros de ningún fichero descargado; no instales nada; no rellenes formularios ni inicies sesión; no accedas a nada que requiera certificado o contraseña. No desactives nunca la verificación SSL.
5. Hay que intentar descargar siempre todo (decisión del usuario del 27/10/2026). El portal de desarrolladores (www.agenciatributaria.es) usa la cadena de certificados de la FNMT (raíz «AC RAIZ FNMT-RCM SERVIDORES SEGUROS»), que `curl` puede no verificar en algunos equipos («SSL certificate problem: self signed certificate in certificate chain»): en ese caso no se usa -k. Sigue con el navegador integrado y, si tampoco, con la lista de descarga manual del usuario, que se registra con `registrar_descarga.py` del núcleo (`<base>` = `Doc/Context/Normativa`). Procedimiento común a las skills de descarga: `../FiscalES/.agents/skills/download-fiscales/NAVEGADOR.md`. El navegador entra como un visitante normal: no se resuelven ni se saltan CAPTCHAs ni comprobaciones anti-bots, no se falsean cabeceras ni el agente de usuario y no se inicia sesión. Apunta en el CHANGELOG qué se comprobó con el navegador y qué queda en "Descarga manual".
   Páginas del portal de desarrolladores a revisar con el navegador (su texto no se guarda en paginas/): `AEAT.desarrolladores/Desarrolladores/_menu_/Documentacion/Pagos_a_cuenta/Modelo_115/Modelo_115.html`, `.../Declaraciones_Informativas/General/General.html`, `.../Documentacion/General/General.html` y el tablón de anuncios (`Desarrolladores.html`), que avisa cuando un modelo de informativas (180) estrena diseño o pasa al entorno de pruebas. El 180 no tiene página propia en el portal (`Modelo_180.html` da 404): se usan el diseño de registro de la Sede y la especificación general de TGVI online.
6. Resumen final (máx. 20 líneas): nº de ficheros comprobados, nuevos, modificados, errores y una lista destacada de los cambios relevantes para un desarrollador (nuevo diseño de registro del 115 o del 180, versión del diseño, claves y subclaves nuevas, órdenes nuevas, plazos, formas de presentación). Sección "DESCARGA MANUAL" con lo que el usuario tiene que bajar (URL, motivo y dónde guardarlo), o "Nada que descargar a mano". Si no hay cambios, dilo en una línea.
7. Si algún documento nuevo o modificado menciona un nuevo diseño de registro, nuevas claves o subclaves, cambios en la forma de presentación o fechas de entrada en vigor, ponlo en una sección "ALERTA" al inicio del resumen, y avisa de que el código del plugin (Doc/Context/04-ficheros-aeat.md) puede necesitar ajustes.
