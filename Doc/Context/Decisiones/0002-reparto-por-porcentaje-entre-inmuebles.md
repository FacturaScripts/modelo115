# 0002. Facturas sin inmueble: único inmueble o reparto por porcentaje

- Fecha: 2026-11-26
- Commits: `f17a63a`
- Estado: vigente

## Contexto

El 180 necesita un registro por arrendador e inmueble, pero no todas las facturas indican el inmueble.

## Decisión

Una factura sin inmueble va al único del proveedor; con varios se reparte según su porcentaje (a partes iguales si
ninguno lo tiene) y el último se queda con el redondeo. Sin inmuebles, registro en situación 4. Todo con avisos.

## Consecuencias

El resultado es una estimación cuando hay reparto; el aviso `model-180-split` lo indica. Elegir el inmueble en la
cabecera del documento evita el reparto.
