# 0001. Solo las retenciones IRPFA entran en el 115

- Fecha: 2026-07-17
- Commits: `8e439e0`
- Estado: vigente

## Contexto

El 115 incluía todas las facturas de proveedor con IRPF, también las de profesionales, que van al modelo 111
(incidencia 110-286-046).

## Decisión

`Lib/Modelo115::filterRentalInvoices()` conserva solo las facturas con partida en una subcuenta de la cuenta
especial `IRPFA`. Sin subcuentas IRPFA en el ejercicio se incluyen todas; las facturas sin asiento se mantienen.

## Consecuencias

La API y la pantalla avisan cuando se aplica una de las excepciones. Quien cambie el filtro debe revisar la
contabilidad de IRPFA y los tests de `Modelo115Test`.
