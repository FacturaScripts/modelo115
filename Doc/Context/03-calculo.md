# Cálculo de los modelos 115 y 180

`Lib/Modelo115::generate()` elige las facturas y suma las casillas; `Lib/Modelo180::build()` las reparte por
arrendador e inmueble.

## Cómo es hoy

**Facturas del modelo 115** (`loadInvoices()`): facturas de proveedor de la empresa del ejercicio, con fecha dentro
del periodo, con `totalirpf` distinto de 0, y con el mismo porcentaje de IRPF que la retención elegida (si se elige).
Periodos: T1 a T4 y `ANNUAL` (año completo); cualquier otro valor se trata como T1. Las fechas salen del año de
inicio del ejercicio.

**Filtro IRPFA** (`filterRentalInvoices()`): solo se quedan las facturas cuyo asiento tiene alguna partida en una
subcuenta de la cuenta especial `IRPFA` (por la cuenta o marcada directamente en la subcuenta). Las retenciones de
profesionales van al modelo 111. Dos excepciones: si el ejercicio no tiene subcuentas IRPFA (`hasRentalSubaccounts()`)
se incluyen todas, y las facturas sin asiento no se pueden clasificar y se mantienen. La API avisa de ambos casos.

**Casillas** (`loadResults()`): 01 perceptores distintos, 02 base (suma de `neto`), 03 retenciones (suma de
`totalirpf`), 04 importe a deducir de autoliquidaciones anteriores del mismo periodo (lo da el usuario) y
05 resultado = 03 - 04. También suma neto, IVA, recargo, IRPF y total de las facturas.

**Reinicio entre llamadas**: `generate()` pone a cero todas las propiedades estáticas al empezar. Antes se
acumulaban bases y retenciones si se llamaba varias veces en la misma petición (corregido en `f17a63a`).

**Reparto del 180** (`Modelo180::addInvoice()` y `getShares()`):

1. Si la factura indica `idinmueble180` y es de un inmueble del proveedor, va entera a él. Si indica uno que no es
   suyo, se avisa y se trata como si no indicara.
2. Sin inmuebles: un registro con inmueble vacío y situación 4 (sin referencia), con aviso.
3. Un solo inmueble: todo a él.
4. Varios: reparto por el `porcentaje` de cada inmueble; si ninguno tiene porcentaje, a partes iguales. El último
   inmueble se queda con el resto del redondeo, para que la suma coincida con la factura.

Cada registro suma base, retenciones y número de facturas; el porcentaje de retención es el de la última factura (por fecha), porque el diseño de registro de la AEAT pide
indicar solo el último cuando en el año se han usado varios.
Los registros se ordenan por proveedor e inmueble. El proveedor aporta NIF, nombre (razón social o nombre) y
provincia del domicilio (por código postal o por nombre). Se avisa de proveedores sin NIF, extranjeros, sin
provincia, sin inmuebles, inmuebles incompletos, repartos y bases negativas. Los avisos son claves de traducción
con parámetros, sin repetir. Los de datos obligatorios del registro (`Modelo180::BLOCKING_WARNINGS`: sin NIF,
extranjero, sin provincia e inmueble incompleto) impiden descargar el fichero; el resto solo informan.

## Por qué

- El filtro IRPFA salió de la incidencia 110-286-046: las retenciones que no son de alquileres no deben ir al 115
  (`8e439e0`, 2026-07-17).
- Mantener las facturas sin asiento y todas las facturas sin subcuentas IRPFA evita que el modelo salga vacío en
  instalaciones que no distinguen cuentas; el aviso lo deja claro. Es una decisión del código, el historial no da
  más motivo.
- Fuera de alcance, según el README: cantidades devengadas en ejercicios anteriores, reintegros, perceptores menores
  de 14 años y rendimientos en especie. Por eso `ejerciciodevengo` va siempre a 0 y la modalidad es dineraria.

## Al cambiarlo

- Los tests `Test/main/Modelo115Test.php` y `Modelo180Test.php` cubren IRPFA, reparto y reinicio.
- Mantén que `generate()` reinicie todo el estado estático al añadir propiedades.
