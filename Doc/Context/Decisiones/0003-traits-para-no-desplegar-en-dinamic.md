# 0003. Código común en traits

- Fecha: 2026-11-26
- Commits: `f17a63a`
- Estado: vigente

## Contexto

Las APIs y los ficheros de los dos modelos comparten mucho código.

## Decisión

Se comparte con `Modelo115ApiTrait` y `Modelo115FileTrait`, no con una clase base, para que no se despliegue en
`Dinamic/Lib`, común a todos los plugins.

## Consecuencias

No crees una clase base compartida; añade el código común a los traits.
