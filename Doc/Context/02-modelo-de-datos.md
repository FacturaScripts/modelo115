# Modelo de datos

El plugin añade una tabla de inmuebles arrendados y una columna opcional en los documentos de compra.

## Cómo es hoy

- Tabla `modelo180_inmuebles` (`Table/modelo180_inmuebles.xml`, modelo `Model/Inmueble180`): un inmueble de un
  proveedor (arrendador). Clave ajena a `proveedores` con `ON DELETE CASCADE`. Guarda situación, referencia
  catastral, dirección normalizada (tipo de vía, nombre, numeración, bloque, portal, escalera, planta, puerta,
  complemento, localidad, municipio, código de municipio, provincia, código postal), descripción libre y
  `porcentaje` de reparto.
- Situación (`Inmueble180::SITUACION_*`): 1 territorio común, 2 País Vasco, 3 Navarra, 4 sin referencia catastral.
- Columna `idinmueble180` (entero, opcional) en `presupuestosprov`, `pedidosprov`, `albaranesprov` y
  `facturasprov`, añadida con `Extension/Table/*.xml`. Clave ajena a `modelo180_inmuebles` con
  `ON DELETE SET NULL`. El modelo 180 solo lee la de la factura.
- `Inmueble180::test()` valida: proveedor obligatorio, situación 1 a 4, referencia catastral obligatoria salvo en
  la 4 (se borra) y de 20 caracteres en la 1, porcentaje entre 0 y 100, código postal y de municipio de 5 dígitos y
  provincia válida; si no hay provincia la deduce del código postal.
- `Inmueble180::missingData()` lista los datos que faltan para declararlo (referencia, vía, municipio, provincia,
  código postal); `Modelo180` lo usa para avisar.
- Al elegir inmueble en un documento, `Mod/PurchasesHeaderHTMLMod` lo borra si es de otro proveedor.

## Por qué

- El registro de perceptor del 180 pide referencia catastral y dirección por inmueble; el historial no lo explica con
  más detalle (`f17a63a`).
- La clave ajena es `SET NULL` para no perder facturas al borrar un inmueble. `Init::update()` crea primero la tabla
  de inmuebles porque la columna apunta a ella.

## Al cambiarlo

- Una columna nueva del inmueble exige tocar `Table/`, `Model/`, `XMLView/EditInmueble180.xml`, el registro en
  `Lib/Modelo180::newRecord()` y `Lib/Modelo180File::getRecipientRecord()`.
- Los códigos de provincia del 180 (01 a 53; 53 = Isla de La Palma, códigos postales 387xx) están en
  `Lib/Modelo180::PROVINCES`.
