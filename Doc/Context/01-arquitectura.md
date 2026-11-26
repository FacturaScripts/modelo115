# Arquitectura

El plugin calcula las retenciones de alquileres (modelo 115 trimestral y resumen anual, modelo 180), muestra el
resultado en una pantalla, genera los ficheros de la AEAT y los expone por la API. No tiene dependencias: el
`facturascripts.ini` no declara `require` (versión 1.7, `min_version` 2026).

## Cómo es hoy

Cada modelo repite la misma estructura: una clase de cálculo, una de respuesta de la API y una del fichero.

| Modelo | Cálculo | API | Fichero |
|---|---|---|---|
| 115 | `Lib/Modelo115` | `Lib/Modelo115Api` | `Lib/Modelo115File` (.115) |
| 180 | `Lib/Modelo180` (sobre el cálculo anual de `Modelo115`) | `Lib/Modelo180Api` | `Lib/Modelo180File` |

Piezas:

- `Lib/Modelo115`: todo método y propiedad es `static`. Es el único sitio donde se eligen las facturas y se suman
  las casillas. Lo usan la pantalla, el fichero y la API, para que den las mismas cifras.
- `Lib/Modelo180`: agrupa las facturas anuales por arrendador e inmueble y devuelve los registros de perceptor y los
  avisos (claves de traducción con parámetros).
- `Lib/Modelo115ApiTrait` y `Lib/Modelo115FileTrait`: código común de las dos APIs (empresa, ejercicio, retención,
  facturas, avisos, formato de fechas e importes) y de los dos ficheros (formato de campos de la AEAT).
- `Controller/Modelo115`: pantalla en Informes > Fiscalidad España. Acciones `print` (PDF), `download` (.115) y
  `download-180`.
- `Controller/ApiModelo115` y `Controller/ApiModelo180`: endpoints de solo lectura (ver
  [`05-api.md`](05-api.md)).
- `Controller/EditInmueble180` y `Extension/Controller/EditProveedor`: edición de inmuebles y su pestaña en la ficha
  del proveedor.
- `Mod/PurchasesHeaderHTMLMod`: selector de inmueble en la cabecera de los documentos de compra.
- `Model/Inmueble180`, `Table/modelo180_inmuebles.xml` y `Extension/Table/*prov.xml`: datos
  (ver [`02-modelo-de-datos.md`](02-modelo-de-datos.md)).
- `Init.php`: registra las rutas `/api/3/modelo115` y `/api/3/modelo180` (y las añade al listado de recursos de la
  API), la extensión del proveedor y el mod de compras. En `update()` crea la tabla de inmuebles antes que las
  columnas de los documentos de compra.
- `Test/main/`: tests de cálculo, 180, ficheros y API.

## Por qué

- Los cálculos salieron del controlador a `Lib/Modelo115` (`04db8d4`, 2026-02-26) para poder reutilizarlos; la
  corrección de acumulación y la API los aprovechan (`f17a63a`).
- Los códigos comunes son traits y no una clase base para que no se desplieguen en `Dinamic/Lib`, que es común a
  todos los plugins (lo dice el docblock de ambos traits).
- Las demás clases usan `FacturaScripts\Dinamic\...` para respetar las extensiones de otros plugins.

## Al cambiarlo

- Un cambio en la elección de facturas se hace solo en `Lib/Modelo115`; revisa pantalla, ficheros y API.
- Añadir una clase común nueva: hazla trait, no clase base.
