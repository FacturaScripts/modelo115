# API REST y permisos

Dos endpoints de solo lectura, pensados para `fs-mcp`.

## Cómo es hoy

- `GET /api/3/modelo115` (`Controller/ApiModelo115` -> `Lib/Modelo115Api`): `periodo` T1-T4 obligatorio (acepta
  1T-4T), `codejercicio` o `idempresa` + `anio`, `detalle`, `codretencion` y `a_deducir`. Devuelve resumen, casillas
  01-05, resultado, facturas y perceptores (con `detalle`) y avisos.
- `GET /api/3/modelo180` (`Controller/ApiModelo180` -> `Lib/Modelo180Api`): `periodo` opcional (solo `ANUAL`),
  `codejercicio` o `idempresa` + `anio`, `detalle` (añade las facturas) y `codretencion`. Devuelve resumen, casillas
  01-03 y siempre el detalle por arrendador e inmueble. `resultado` es `null` (declaración informativa).
- Errores como `{"status": "error", "message": ...}`: 405 `method-not-allowed`; 400 `missing-period`,
  `invalid-period`, `invalid-parameter`, `invalid-year`; 404 `exercise-not-found`, `company-not-found`,
  `retention-not-found`.
- Los avisos de la API van en español fijo (`Tools::lang('es_ES')`), no en el idioma del usuario, igual que en las
  APIs de los demás plugins de modelos fiscales: los consume fs-mcp, que trabaja en español.
- `a_deducir` (casilla 04) solo admite importes positivos o 0, como `ingresos_anteriores` en el Modelo111.
- Sin `codejercicio`, el ejercicio es el de la empresa (la predeterminada si no se indica) cuya fecha de inicio cae
  en el año pedido.
- Permisos: una clave API sin acceso completo necesita el permiso de lectura del recurso `modelo115` o `modelo180`
  (según los docblocks de los controladores). Las rutas se registran en `Init.php` con `Kernel::addRoute` y
  `ApiRoot::addCustomResource`. No hay endpoints de escritura.

## Por qué

- Se añadieron en `f17a63a` para que `fs-mcp` consulte los modelos sin tocar datos. Usan las mismas `Lib` que la
  pantalla, así que dan las mismas cifras.

## Al cambiarlo

- Un parámetro nuevo se añade en la constante `PARAMS` del controlador y en la `Lib` de la API.
- Pruébalo con `Test/main/Modelo115ApiTest.php` y `Modelo180ApiTest.php`.
