# Modelo115
Plugin para FacturaScripts con los modelos 115 y 180 para la hacienda española.
- https://facturascripts.com/plugins/modelo115

Muestra los datos de ambos modelos desde el menú **Informes → Modelos fiscales → Modelo 115 y 180**, con el detalle de las facturas del periodo, totales e impresión en PDF.

Solo se incluyen en el cálculo las facturas cuya retención se contabiliza en una subcuenta con la cuenta especial **IRPFA** (IRPF para alquileres); las retenciones de profesionales y trabajadores corresponden al modelo 111 y quedan fuera. Si el ejercicio no tiene ninguna subcuenta IRPFA, se incluyen todas las facturas con retención.

## Documentación
- [Cómo hacer el Modelo 115](https://facturascripts.com/publicaciones/como-hacer-el-modelo-115)
- [Cómo hacer el Modelo 180](https://facturascripts.com/publicaciones/como-hacer-el-modelo-180)

## Issues / Feedback
https://facturascripts.com/contacto

## Tests
Los tests se ejecutan en GitHub Actions contra MySQL y PostgreSQL con cada push. Para lanzarlos en local, copia `Test/main` a `Test/Plugins` en un FacturaScripts con el plugin instalado y ejecuta `vendor/bin/phpunit -c phpunit-plugins.xml`.

## Links
- [Curso de FacturaScripts](https://facturascripts.com/cursos/curso-de-usuario)
- [Programa de contabilidad gratis para autónomos](https://facturascripts.com/software-contabilidad)
- [Programa para hacer facturas gratis](https://facturascripts.com/programa-para-hacer-facturas)
- [Programa para hacer presupuestos gratis](https://facturascripts.com/programa-de-presupuestos)
