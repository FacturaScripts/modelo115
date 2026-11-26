# 0004. API de solo lectura

- Fecha: 2026-11-26
- Commits: `f17a63a`
- Estado: vigente

## Contexto

`fs-mcp` necesita consultar los modelos.

## Decisión

Solo `GET` (`ApiModelo115`, `ApiModelo180`); cualquier otro método devuelve 405. No crean ni modifican datos.

## Consecuencias

Las acciones de generar fichero siguen en la pantalla.
