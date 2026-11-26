El plugin Modelo115 expone los datos de los modelos 115 (retenciones trimestrales de alquileres) y 180 (resumen anual) en dos endpoints de solo lectura de la API REST. Calculan con las mismas clases que la pantalla del plugin, así que las cifras coinciden con las que se ven en **menú Informes** → **Modelos fiscales** → **Modelo 115 y 180**. No crean asientos, no guardan datos y no generan los ficheros de la AEAT.

- `GET /api/3/modelo115`: un trimestre. Devuelve el resumen, las casillas y el resultado a ingresar.
- `GET /api/3/modelo180`: el año completo. Devuelve el resumen, tres casillas y el detalle por arrendador e inmueble. Es una declaración informativa y su `resultado` es `null`.

## 1. Autenticación y permisos

La API tiene que estar activada y hace falta una clave API, que se envía en la cabecera `Token` (también vale `X-Auth-Token`). Una clave con **acceso completo** puede llamar a los dos endpoints. Una clave sin acceso completo necesita, para cada endpoint, el permiso de lectura (`GET`) del recurso `modelo115` o `modelo180`; el plugin añade los dos recursos al listado de recursos de la API (`ApiRoot::addCustomResource()` en `Init.php`).

```bash
curl -H "Token: TU_CLAVE_API" \
  "https://tu-servidor/api/3/modelo115?periodo=T1&codejercicio=2025"
```

## 2. Parámetros

Todos van en la query string y los mismos nombres sirven para los dos endpoints, salvo los indicados.

| Parámetro | Endpoint | Descripción |
|---|---|---|
| `periodo` | 115 | **Obligatorio.** `T1`, `T2`, `T3` o `T4` (también `1T`-`4T`, en cualquier combinación de mayúsculas y minúsculas). |
| `periodo` | 180 | Opcional. Solo `ANUAL` (también `ANNUAL`, `Y`, `A` y `0A`), que es el valor por defecto. |
| `codejercicio` | ambos | Código del ejercicio. Si se indica, manda sobre `idempresa` y `anio`. |
| `idempresa` | ambos | Empresa. Por defecto, la predeterminada del programa. |
| `anio` | ambos | Año de cuatro cifras. Por defecto, el actual. Se usa el ejercicio de la empresa cuya fecha de inicio cae en ese año. |
| `detalle` | ambos | `1`, `true`, `yes`, `si`, `sí` u `on` para incluir las facturas. En el 115 también añade el detalle por perceptor; en el 180 el detalle por arrendador e inmueble va siempre. |
| `codretencion` | ambos | Código de una retención: solo se tienen en cuenta las facturas con esa retención. Vacío (por defecto), todas. |
| `a_deducir` | 115 | Importe ya ingresado en autoliquidaciones anteriores del mismo periodo (casilla 04). Se pasa en positivo y se resta de las retenciones: casilla 05 = casilla 03 − casilla 04. Acepta coma decimal. Por defecto, `0`. |

El modelo 115 no admite el periodo anual: el resumen anual está en `/api/3/modelo180`.

## 3. Estructura de la respuesta

Los dos endpoints devuelven un objeto JSON con estos campos:

- `modelo`: `"115"` o `"180"`.
- `plugin_version`: versión del plugin, leída de la configuración del plugin.
- `empresa`: `idempresa`, `nombre` y `cifnif`.
- `ejercicio`: `codejercicio`, `nombre`, `fechainicio`, `fechafin` (en formato `Y-m-d`) y `estado`.
- `periodo`, `fechainicio` y `fechafin`: el periodo calculado y sus fechas. Los trimestres y el año se cuentan en el año natural en el que empieza el ejercicio.
- `parametros`: en el 115, `codretencion` y `a_deducir`; en el 180, solo `codretencion`.
- `resumen`, `casillas` y `resultado`: ver más abajo.
- `detalle`: facturas y perceptores (ver cada modelo).
- `avisos`: lista de textos en español sobre los datos del modelo.

Las cifras salen redondeadas a dos decimales. Los `avisos` pueden decir que el ejercicio no tiene subcuentas de retenciones de alquileres (y entonces se incluyen todas las facturas de proveedor con IRPF, también las de profesionales), que hay facturas con IRPF sin asiento contable, que no hay facturas con retenciones de alquileres en el periodo y, en el 180, los avisos de los perceptores e inmuebles: proveedor sin NIF, sin provincia o sin domicilio en España, inmueble al que le faltan datos, base negativa, factura con un inmueble de otro proveedor, arrendador sin inmuebles o reparto de facturas entre varios inmuebles.

### Modelo 115

`resumen` contiene `perceptores`, `base`, `retenciones`, `a_deducir`, `resultado`, `num_facturas`, `total_neto`, `total_iva`, `total_recargo`, `total_irpf` y `total`. `casillas` usa la numeración oficial: `01` (perceptores), `02` (base de las retenciones), `03` (retenciones), `04` (resultados de autoliquidaciones anteriores) y `05` (resultado a ingresar). `resultado` repite la casilla `05`.

```bash
curl -H "Token: TU_CLAVE_API" \
  "https://tu-servidor/api/3/modelo115?periodo=T1&idempresa=1&anio=2025"
```

```json
{
  "modelo": "115",
  "plugin_version": 1.7,
  "empresa": { "idempresa": 1, "nombre": "Empresa Ejemplo SL", "cifnif": "B00000001" },
  "ejercicio": {
    "codejercicio": "2025",
    "nombre": "Ejercicio 2025",
    "fechainicio": "2025-01-01",
    "fechafin": "2025-12-31",
    "estado": "ABIERTO"
  },
  "periodo": "T1",
  "fechainicio": "2025-01-01",
  "fechafin": "2025-03-31",
  "parametros": { "codretencion": "", "a_deducir": 0 },
  "resumen": {
    "perceptores": 2,
    "base": 6000,
    "retenciones": 1140,
    "a_deducir": 0,
    "resultado": 1140,
    "num_facturas": 4,
    "total_neto": 6000,
    "total_iva": 1260,
    "total_recargo": 0,
    "total_irpf": 1140,
    "total": 6120
  },
  "casillas": { "01": 2, "02": 6000, "03": 1140, "04": 0, "05": 1140 },
  "resultado": 1140,
  "avisos": []
}
```

Con `detalle=1` se añade `detalle.facturas` y `detalle.perceptores`. Cada factura trae `idfactura`, `codigo`, `fecha`, `codproveedor`, `nombre`, `cifnif`, `neto`, `irpf`, `totalirpf` y `total`. Cada perceptor (un proveedor, ordenado por código) trae `codproveedor`, `nombre`, `cifnif`, `base`, `retenciones` y `num_facturas`.

```json
"detalle": {
  "facturas": [
    {
      "idfactura": 20,
      "codigo": "FAC2025A2",
      "fecha": "2025-01-01",
      "codproveedor": "P0001",
      "nombre": "Arrendador Ejemplo 1",
      "cifnif": "00000001R",
      "neto": 1200,
      "irpf": 19,
      "totalirpf": 228,
      "total": 1224
    }
  ],
  "perceptores": [
    {
      "codproveedor": "P0001",
      "nombre": "Arrendador Ejemplo 1",
      "cifnif": "00000001R",
      "base": 3600,
      "retenciones": 684,
      "num_facturas": 3
    }
  ]
}
```

### Modelo 180

`resumen` contiene `num_registros` (los registros de perceptor, uno por arrendador e inmueble), `base`, `retenciones` y `num_facturas`. `casillas` solo tiene `01` (registros), `02` (base) y `03` (retenciones). `resultado` es siempre `null`. Las fechas son las del año natural en el que empieza el ejercicio.

```bash
curl -H "Token: TU_CLAVE_API" \
  "https://tu-servidor/api/3/modelo180?idempresa=1&anio=2025&detalle=1"
```

```json
{
  "modelo": "180",
  "plugin_version": 1.7,
  "empresa": { "idempresa": 1, "nombre": "Empresa Ejemplo SL", "cifnif": "B00000001" },
  "ejercicio": {
    "codejercicio": "2025",
    "nombre": "Ejercicio 2025",
    "fechainicio": "2025-01-01",
    "fechafin": "2025-12-31",
    "estado": "ABIERTO"
  },
  "periodo": "ANUAL",
  "fechainicio": "2025-01-01",
  "fechafin": "2025-12-31",
  "parametros": { "codretencion": "" },
  "resumen": { "num_registros": 2, "base": 19200, "retenciones": 3648, "num_facturas": 14 },
  "casillas": { "01": 2, "02": 19200, "03": 3648 },
  "resultado": null,
  "detalle": {
    "perceptores": [
      {
        "codproveedor": "P0001",
        "nombre": "Arrendador Ejemplo 1",
        "cifnif": "00000001R",
        "codprovincia": "28",
        "modalidad": 1,
        "base": 14400,
        "porcentaje": 19,
        "retenciones": 2736,
        "ejercicio_devengo": 0,
        "situacion": 1,
        "inmueble": {
          "idinmueble": 1,
          "descripcion": "Local comercial Ejemplo",
          "situacion": 1,
          "refcatastral": "0000000AA0000A0001AA",
          "tipovia": "CL",
          "nombrevia": "Ejemplo",
          "tiponumeracion": "NUM",
          "numero": "12",
          "calificador": "",
          "bloque": "",
          "portal": "",
          "escalera": "",
          "planta": "",
          "puerta": "",
          "complemento": "",
          "localidad": "",
          "municipio": "Municipio Ejemplo",
          "codmunicipio": "28079",
          "codprovincia": "28",
          "codpostal": "28013"
        },
        "reparto": false,
        "num_facturas": 12,
        "facturas": ["FAC2025A2", "FAC2025A5", "FAC2025A7"]
      },
      {
        "codproveedor": "P0002",
        "nombre": "Arrendador Ejemplo 2",
        "cifnif": "B00000002",
        "codprovincia": "28",
        "modalidad": 1,
        "base": 4800,
        "porcentaje": 19,
        "retenciones": 912,
        "ejercicio_devengo": 0,
        "situacion": 1,
        "inmueble": {
          "idinmueble": 2,
          "descripcion": "Nave almacén Ejemplo",
          "situacion": 1,
          "refcatastral": "0000000AA0000A0002AA",
          "tipovia": "AV",
          "nombrevia": "de Ejemplo",
          "tiponumeracion": "NUM",
          "numero": "40",
          "calificador": "",
          "bloque": "",
          "portal": "",
          "escalera": "",
          "planta": "",
          "puerta": "",
          "complemento": "",
          "localidad": "",
          "municipio": "Municipio Ejemplo",
          "codmunicipio": "28065",
          "codprovincia": "28",
          "codpostal": "28906"
        },
        "reparto": false,
        "num_facturas": 2,
        "facturas": ["FAC2025A3", "FAC2025A10"]
      }
    ],
    "facturas": [
      {
        "idfactura": 20,
        "codigo": "FAC2025A2",
        "fecha": "2025-01-01",
        "codproveedor": "P0001",
        "nombre": "Arrendador Ejemplo 1",
        "cifnif": "00000001R",
        "neto": 1200,
        "irpf": 19,
        "totalirpf": 228,
        "total": 1224
      }
    ]
  },
  "avisos": []
}
```

Los ejemplos son respuestas de una instalación de pruebas, abreviadas y con los datos personales sustituidos por valores ficticios. `detalle.facturas` solo aparece con `detalle=1`, y es la lista completa de las facturas del año.

Cada elemento de `detalle.perceptores` es un registro del fichero del modelo 180, con estos campos:

| Campo | Contenido |
|---|---|
| `codproveedor`, `nombre`, `cifnif` | Arrendador. El nombre es la razón social o, si no tiene, el nombre del proveedor. |
| `codprovincia` | Provincia del arrendador (código de 2 dígitos del modelo 180), sacada del código postal o del nombre de la provincia de su dirección. |
| `modalidad` | Modalidad de la percepción: `1` (dineraria). |
| `base`, `porcentaje`, `retenciones` | Base y retención del arrendador en ese inmueble, y el porcentaje de retención de su última factura por fecha (la AEAT pide solo el último si hay varios). |
| `ejercicio_devengo` | `0`, salvo en percepciones de ejercicios anteriores. |
| `situacion` | Situación del inmueble, de `1` a `4`. Sin inmueble, vale `4`. |
| `inmueble` | Datos del inmueble o `null` si el arrendador no tiene ninguno. Con `idinmueble`, `descripcion`, `situacion`, `refcatastral`, la dirección (`tipovia`, `nombrevia`, `tiponumeracion`, `numero`, `calificador`, `bloque`, `portal`, `escalera`, `planta`, `puerta`, `complemento`, `localidad`, `municipio`, `codmunicipio`, `codprovincia` y `codpostal`). |
| `reparto` | `true` si parte de la base sale del reparto de facturas sin inmueble entre varios inmuebles del proveedor. |
| `num_facturas`, `facturas` | Número y códigos de las facturas que suman en el registro. |

Cada factura va a su inmueble si lo indica; si no, al único inmueble del proveedor o, si tiene varios, se reparte según el porcentaje de cada uno (a partes iguales si ninguno lo tiene). Un proveedor sin inmuebles genera un registro con `inmueble` a `null` y `situacion` 4, y un aviso. El último inmueble del reparto se queda con el resto del redondeo, para que la suma coincida con la factura.

## 4. Errores

Los errores de los endpoints del plugin tienen la forma `{"status": "error", "message": "..."}`, y algunos añaden datos extra.

| Código | `message` | Cuándo |
|---|---|---|
| 400 | `missing-period` | Falta `periodo` en el 115 (`allowed` lista los valores válidos). |
| 400 | `invalid-period` | Periodo no válido, o distinto de `ANUAL` en el 180 (`allowed`). |
| 400 | `invalid-parameter` | `a_deducir` no es un número o es negativo (`parameter`). |
| 400 | `invalid-year` | `anio` no tiene cuatro cifras (`anio`). |
| 404 | `exercise-not-found` | No existe el ejercicio (`codejercicio`) o la empresa no tiene ejercicio en ese año (`idempresa` y `anio`). |
| 404 | `company-not-found` | No existe la empresa (`idempresa`). |
| 404 | `retention-not-found` | No existe la retención (`codretencion`). |
| 405 | `method-not-allowed` | Cualquier método distinto de `GET`. |

Los errores de acceso los genera el core, con el mismo formato y el texto en `message`: 401 si la clave no es válida, 403 si la clave no tiene permiso de lectura sobre el recurso, 409 si la API está desactivada y 429 si la IP está bloqueada o se supera el límite de peticiones por minuto.

## 5. Usarlo desde otro plugin o desde un cliente

Desde PHP, otro plugin puede llamar a la clase de la API sin pasar por HTTP. `build()` devuelve el código HTTP y el cuerpo ya construido:

```php
use FacturaScripts\Dinamic\Lib\Modelo115Api;
use FacturaScripts\Dinamic\Lib\Modelo180Api;

$r115 = Modelo115Api::build(['periodo' => 'T1', 'codejercicio' => '2025']);
$r180 = Modelo180Api::build(['idempresa' => 1, 'anio' => 2025, 'detalle' => '1']);

if ($r115['code'] === 200) {
    $aIngresar = $r115['body']['casillas']['05'];
}
```

Los parámetros son los mismos de la query string. Desde fuera del servidor, basta una petición `GET` con la clave API.

El servidor MCP del ERP envuelve estos endpoints en dos herramientas de solo lectura, `get_modelo_115` y `get_modelo_180`, que aceptan `idempresa` y `anio` o `codejercicio`, `periodo`, `detalle` y `codretencion` (y `a_deducir` en el 115). Requieren una versión del plugin que tenga los endpoints.

## 6. Leer o ampliar los inmuebles

Los inmuebles arrendados que usa el modelo 180 son un modelo normal del plugin, que otros plugins pueden leer o ampliar.

- **`Dinamic\Model\Inmueble180`** (tabla `modelo180_inmuebles`): un inmueble de un proveedor (`codproveedor`), con `descripcion`, `situacion` (constantes `SITUACION_COMUN` = 1, `SITUACION_PAIS_VASCO` = 2, `SITUACION_NAVARRA` = 3 y `SITUACION_SIN_REFERENCIA` = 4), `refcatastral`, la dirección normalizada (`tipovia`, `nombrevia`, `tiponumeracion`, `numero`, `calificador`, `bloque`, `portal`, `escalera`, `planta`, `puerta`, `complemento`, `localidad`, `municipio`, `codmunicipio`, `codprovincia` y `codpostal`) y `porcentaje` de reparto. Al borrar el proveedor se borran sus inmuebles.
- **`missingData()`** devuelve las claves de traducción de los datos que faltan para declarar el inmueble (referencia catastral, nombre de la vía, municipio, provincia y código postal) y **`primaryDescription()`**, su descripción o, si no tiene, su dirección.
- **`test()`** valida la situación, la referencia catastral, el porcentaje y los códigos, y calcula la provincia con el código postal si falta.
- **`idinmueble180`**: columna añadida por el plugin a `presupuestosprov`, `pedidosprov`, `albaranesprov` y `facturasprov` (`Extension/Table/*.xml`), con clave ajena a `modelo180_inmuebles` y `ON DELETE SET NULL`. Es `null` si el documento no indica inmueble.

```php
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Inmueble180;

// inmuebles de un proveedor
$where = [Where::eq('codproveedor', 'P0001')];
foreach (Inmueble180::all($where, ['id' => 'ASC']) as $inmueble) {
    echo $inmueble->primaryDescription() . ' ' . $inmueble->refcatastral . "\n";
}

// facturas de un proveedor que indican un inmueble concreto
$where = [Where::eq('codproveedor', 'P0001'), Where::eq('idinmueble180', 1)];
$facturas = FacturaProveedor::all($where);
```

El selector de la cabecera de las compras es **`Plugins\Modelo115\Mod\PurchasesHeaderHTMLMod`**, un `PurchasesModInterface` registrado en `Init.php` con `PurchasesHeaderHTML::addMod()`. Pinta el campo **Inmueble (modelo 180)** en el modal **Detalle** (`newModalFields()` devuelve `inmueble180`), guarda `idinmueble180` en `applyBefore()` y, en `apply()`, lo quita si el inmueble no existe o es de otro proveedor. Un plugin que cree documentos de compra por código puede rellenar `idinmueble180` antes de guardar.

La pestaña **Inmuebles (modelo 180)** de la ficha del proveedor la añade `Extension/Controller/EditProveedor.php` con una `ListView` (`ListInmueble180`) cuyo botón de alta y sus filas llevan a `EditInmueble180`, un `EditController` del plugin que no sale en el menú.

## 7. Estructura del código

Cada modelo tiene una clase de cálculo, una de respuesta de la API y una del fichero de la AEAT.

| Archivo | Qué hace |
|---|---|
| `Init.php` | Registra las rutas con `Kernel::addRoute()`, los recursos con `ApiRoot::addCustomResource()`, la pestaña de inmuebles y el selector de las compras. |
| `Controller/ApiModelo115.php`, `Controller/ApiModelo180.php` | Extienden `ApiController`: validan que el método sea `GET`, leen la query string y responden con el resultado de `build()`. |
| `Lib/Modelo115.php` | Cálculo del modelo 115 y del año completo: `generate($codejercicio, $periodo, $codretencion, $todeduct)`. El periodo anual se pide como `ANNUAL`. |
| `Lib/Modelo180.php` | Agrupa las facturas del año por arrendador e inmueble (`build($facturas)`), reparte las que no indican inmueble y genera los avisos, como claves de traducción. Tiene también los códigos de provincia. |
| `Lib/Modelo115Api.php`, `Lib/Modelo180Api.php` | Construyen la respuesta de cada endpoint con `build(array $params)`. |
| `Lib/Modelo115File.php`, `Lib/Modelo180File.php` | Generan los ficheros de presentación de la AEAT. |
| `Model/Inmueble180.php` | Inmueble arrendado de un proveedor (`Table/modelo180_inmuebles.xml`). |

`Lib/Modelo115ApiTrait.php` (empresa, ejercicio, periodo, retención, facturas y avisos) y `Lib/Modelo115FileTrait.php` (formato de los campos de los ficheros) son traits, no clases base, para que no se desplieguen en `Dinamic/Lib`, que es común a todos los plugins. Para ampliar la API, por ejemplo con un campo nuevo en los registros del 180, se añade en el método correspondiente del trait o de la clase `*Api`; para usar las clases desde otro plugin, se importan siempre desde el espacio de nombres `Dinamic`.
