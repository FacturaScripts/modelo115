# CHANGELOG – Documentación oficial de los modelos 115 y 180

## 2026-11-26 (primera ejecución, carpeta vacía)

### Qué se ha descargado
- **Páginas (46)** en `paginas/`, con la URL de origen en la primera línea: ayuda técnica del 115 (presentación
  electrónica, con datos anteriores y en papel) y del 180 (formulario, fichero y cómo emitir certificados), gestiones y
  obligaciones del retenedor (`modelo-115`, `modelo-180`), ficha del procedimiento GH02, normativa del 180 en la campaña
  de informativas 2025, plazos, novedades, cuestiones relevantes, notas informativas, ejercicios anteriores del 180,
  guía práctica del censal 036 (capítulo 9), folleto de actividades económicas (7.3, modelos 115 y 180) y el manual de
  sociedades sobre retenciones por arrendamientos.
- **Diseños de registro** (`actual/`): `DR115e15v13.xls` (115, v 1.3, Orden EHA/3435/2007, ejercicios 2019 y siguientes,
  actualizado en febrero de 2019) y `DR_Mod_180_2023.pdf` (180, Orden HAP/1732/2014 actualizada por la Orden
  HFP/1284/2023).
- **Otros documentos** (`actual/`): `NOVEDADES_2025.pdf` y `RECORDATORIOS_INF_2025.pdf` de la campaña de informativas, y
  del portal de desarrolladores `Especificaciones_TGVI_Online.pdf`, `EspecificacionesServiciosDeclaracionesAEAT_ES.pdf` y
  `consultas.xsd` (copiados de Modelo111, ver más abajo).
- **Normativa del BOE** (`actual/normativa/`, 16 textos consolidados): Orden de 20 de noviembre de 2000 (aprueba los
  modelos 115 y 180), HAC/540/2003, EHA/3435/2007 (diseño del 115) y HAC/1417/2018 (la modifica), EHA/3062/2010,
  HAP/2725/2012, HAP/2194/2013, HAP/1732/2014, HAC/1276/2020, HFP/1351/2021 y HFP/1284/2023 (formas de presentación y
  diseños del 180), más la base legal: Ley 35/2006 del IRPF, RD 439/2007, Ley 27/2014 del IS, RD 634/2015 y el
  RDLeg 5/2004 (IRNR).
- **No se ha descargado a propósito** el material de retenciones de rendimientos del trabajo (cuadros de tipos, algoritmo,
  certificado único): es del modelo 111 y no aplica a alquileres, cuyo tipo es fijo (19 %). Está en el plugin Modelo111.

### Versiones vigentes comprobadas
- Modelo 115: diseño de registro **v 1.3** (febrero de 2019); página de ayuda técnica actualizada el 19/11/2026 y ficha
  GH02 del 03/08/2026. Sin cambios de diseño recientes. Se presenta por formulario web, por fichero (presentación por
  lotes) o en papel (predeclaración). Plazo: los 20 primeros días naturales siguientes al trimestre (grandes empresas:
  mensual).
- Modelo 180: diseño de registro de la Orden HFP/1284/2023 (PDF de 2023, publicado de nuevo el 20/11/2025); la página de
  diseños de registro está actualizada el 29/09/2026 y no muestra ningún diseño del 180 para 2025 ni 2026. Páginas de
  ayuda técnica del 180 actualizadas el 19/11/2026. Se presenta por formulario web (hasta 40.000 registros) o por
  fichero; ambas vías son excluyentes.
- La Sede no tiene página de «instrucciones» del 115 en `todas-gestiones/.../pagos-cuenta` (el 111 sí); la información de
  uso está en las gestiones del retenedor y en la guía práctica del censal 036.

### Descarga manual (el portal de desarrolladores no se pudo leer con curl) – HECHA el 2026-11-26
Los dos ficheros propios del 115 los descargó el usuario y se registraron con `registrar_descarga.py` (`--origen manual`);
su SHA-256 y tamaño coinciden con los publicados. Nada pendiente.
`www.agenciatributaria.es` devuelve «SSL certificate problem: self signed certificate in certificate chain» (la raíz
«AC RAIZ FNMT-RCM SERVIDORES SEGUROS» no está en la lista de confianza de este equipo) y no se desactiva la
verificación. Con el navegador integrado se comprobó que los cinco ficheros existen (HTTP 200) y se calculó su SHA-256;
el navegador no guarda en disco. Tres son comunes con el 111 y ya están registrados (`--origen manual`, copiados de
`Modelo111/Doc/Context/Normativa/actual/` con el mismo SHA-256 publicado). **Faltan los dos propios del 115**, que tiene
que bajó el usuario a mano (ya registrados):

| Fichero (base `https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/`) | Tamaño | Last-Modified | SHA-256 publicado |
|---|---|---|---|
| `Modelo_115/espes115e22v10.docx` (115, ejercicios 2022 y siguientes, v 1.0) | 34.366 | 14/04/2023 | `ada94c11f54a54241a2ca5ba206108fd8cb539b8361f11c68b5d8ebee0078dad` |
| `Modelo_115/espes115e19v11.docx` (115, ejercicios 2019 a 2021, v 1.1) | 98.589 | 25/01/2019 | `54cc2650fa02522951ddcd3a6c0b1493c1268ecd2bc1fb7621190c049db14c99` |

Registro tras descargarlos: `python3 -I ../FiscalES/.agents/skills/download-fiscales/scripts/registrar_descarga.py
Doc/Context/Normativa <url> <fichero> --ruta actual/<nombre> --origen manual`.

Ya registrados (SHA-256 coincide con el publicado): `Informativas/General/Especificaciones_TGVI_Online.pdf` (TGVI online,
v 2.8, 17/10/2024), `General/EspecificacionesServiciosDeclaracionesAEAT_ES.pdf` (05/11/2026) y `General/consultas.xsd`
(17/09/2020).

Páginas del portal leídas con el navegador (su texto no se guarda en `paginas/`): `Documentacion/Pagos_a_cuenta/Modelo_115`
y `Documentacion/Declaraciones_Informativas/General`. Para el 180 el portal no publica documento propio
(`Modelo_180.html` devuelve 404): se usa el diseño de registro de la Sede y la especificación general de TGVI online.

### Resultado
- 25 ficheros en el manifest (20 por script y 5 manuales del portal de desarrolladores). Sin nombres largos. Nada pendiente de descarga manual.
