# Evolución

Etapas según el historial git (38 commits).

| Fecha | Commit | Qué cambió |
|---|---|---|
| 2020-05 | `e72de18`, `4790173` | Versión inicial: un controlador con plantilla twig que lista facturas con IRPF y calcula el 115; opción de excluir una retención. |
| 2022-03 a 2025-01 | `f3851bc`, `ba6b3b1`, `324a4af` | Mantenimiento: traducciones, compatibilidad con el core 2022.6, selector de ejercicio separado por empresas. |
| 2025-08 | `70c18da`, `615ffb6`, `665e9f7` | Versión mínima 2025, Bootstrap 5, métodos estáticos de `Retencion` y `FacturaProveedor` (v1.5). |
| 2026-02-26 | `04db8d4` | El cálculo pasa a `Lib/Modelo115` y se añade el periodo anual (modelo 180) (v1.6). |
| 2026-05 | `bcad318` | Impresión en PDF y totales de facturas (roadmap 2880). Flujos de release en GitHub. |
| 2026-07-17 | `bc3778a`, `8e439e0`, `803e387`, `cafb80c` | Primeros tests con CI; filtro IRPFA (incidencia 110-286-046); página en el submenú de modelos fiscales; versión 1.7, mínimo 2026. |
| 2026-10/11 | `46199e3`, `63448b6` | CI: PHP 8.5 y tests solo si cambia el código. |
| 2026-11-26 | `f17a63a` | Fichero 180 y 115, inmuebles, extensiones de tablas y Mod, API, estructura Modelo115/Modelo180 + *Api + *File con traits, corrección de acumulación entre llamadas y claves de traducción en minúsculas. |

Partes abandonadas: no hay.

## Por qué

El grueso del plugin actual se escribió en un solo commit (`f17a63a`); hasta entonces era un controlador con
plantilla. El historial no da más motivos que los mensajes de commit.
