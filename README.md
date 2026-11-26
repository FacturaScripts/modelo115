# Modelo115
Plugin para FacturaScripts con los modelos 115 y 180 para la hacienda española.
- https://facturascripts.com/plugins/modelo115

Muestra los datos de ambos modelos desde el menú **Informes → Fiscalidad España → Modelo 115 y 180**, con el detalle de las facturas del periodo, totales, impresión en PDF y descarga del fichero de presentación de la AEAT (.115 en los trimestres y del 180 en el anual).

Solo se incluyen en el cálculo las facturas cuya retención se contabiliza en una subcuenta con la cuenta especial **IRPFA** (IRPF para alquileres); las retenciones de profesionales y trabajadores corresponden al modelo 111 y quedan fuera. Si el ejercicio no tiene ninguna subcuenta IRPFA, se incluyen todas las facturas con retención.

## Modelo 180: fichero de presentación
En el periodo **Anual (Modelo 180)** la pantalla muestra un registro por arrendador e inmueble y descarga el fichero del 180 según el diseño de registro de la AEAT (vigente desde el ejercicio 2023): registro de tipo 1 con el declarante y los totales, y registros de tipo 2 con el perceptor y el inmueble, de 500 posiciones, en ISO-8859-1.

- **Inmuebles:** se rellenan en la pestaña **Inmuebles (modelo 180)** de la ficha de cada proveedor: situación, referencia catastral y dirección normalizada (tipo de vía del INE, código de municipio, etc.).
- **Facturas e inmuebles:** en el detalle de la cabecera de pedidos, albaranes y facturas de proveedor se puede elegir el inmueble. Si se deja en automático, se usa el único inmueble del proveedor o, si tiene varios, la base se reparte según el porcentaje de cada inmueble (a partes iguales si ninguno lo tiene). Los proveedores sin inmuebles se declaran sin referencia catastral (situación 4).
- **Avisos:** la pantalla avisa de los proveedores sin NIF, sin provincia o sin inmuebles, de los inmuebles incompletos y de los repartos.
- **Fuera de alcance:** cantidades devengadas en ejercicios anteriores, reintegros, perceptores menores de 14 años y rendimientos en especie.

## Estructura y API
Cada modelo tiene la misma estructura: una clase de cálculo, una de respuesta de la API y una del fichero de la AEAT.

| Modelo | Cálculo | API | Fichero | Endpoint |
|---|---|---|---|---|
| 115 (trimestral) | `Lib/Modelo115` | `Lib/Modelo115Api` | `Lib/Modelo115File` (.115) | `GET /api/3/modelo115` |
| 180 (resumen anual) | `Lib/Modelo180` (con el cálculo anual de `Modelo115`) | `Lib/Modelo180Api` | `Lib/Modelo180File` | `GET /api/3/modelo180` |

Las dos clases de la API comparten `Lib/Modelo115ApiTrait` (empresa, ejercicio, retención, facturas y avisos) y los dos ficheros, `Lib/Modelo115FileTrait` (formato de los campos). Son traits para que no se desplieguen en `Dinamic/Lib`.

- **Fichero del 115:** en los trimestres, la pantalla descarga el fichero `.115` según el diseño de registro de la AEAT DR115 (ejercicios 2019 y siguientes): cabecera, página 01 de 500 posiciones con las casillas 01-05 y cierre, en ISO-8859-1. Se elige el tipo de declaración (por defecto, I si hay importe a ingresar y N si no), el IBAN para domiciliar y, en las complementarias (con importe a deducir), el justificante de la declaración anterior.
- `GET /api/3/modelo115`: `periodo` T1-T4 (obligatorio), `codejercicio` o `idempresa` y `anio`, `detalle` (facturas y perceptores), `codretencion` y `a_deducir`. Devuelve el resumen, las casillas 01-05 y el resultado (casilla 05).
- `GET /api/3/modelo180`: `periodo` opcional (solo `ANUAL`), `codejercicio` o `idempresa` y `anio`, `detalle` (facturas) y `codretencion`. Devuelve el resumen, las casillas 01-03 del resumen y siempre el detalle por arrendador e inmueble, con los mismos registros del fichero. Es informativo: `resultado` vale `null`.

Una clave API sin acceso completo necesita el permiso de lectura del recurso `modelo115` o `modelo180`.

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
