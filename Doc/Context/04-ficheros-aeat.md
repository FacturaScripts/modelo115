# Ficheros de presentación de la AEAT

El plugin genera el fichero del modelo 115 (`.115`) y el del 180, los dos en ISO-8859-1.

## Cómo es hoy

**Formato común** (`Lib/Modelo115FileTrait`): textos en mayúsculas, sin tildes ni caracteres especiales (se
conservan la Ñ y la Ç), ajustados a la izquierda y rellenos de blancos; numéricos a la derecha y con ceros a la
izquierda; importes en céntimos sin coma ni signo; porcentajes con 2 enteros y 2 decimales; NIF de 9 posiciones. El
texto se pasa a ISO-8859-1 antes de rellenar para que la Ñ ocupe un byte.

**Modelo 115** (`Lib/Modelo115File`, diseño DR115e15v13, ejercicios 2019 y siguientes): cabecera
`<T1150AAAAPP0000><AUX>...</AUX>` de 328 posiciones, página 01 de 500 y cierre. Casillas 01 a 05 salen de
`Modelo115::generate()`. Tipo de declaración: `I` ingreso, `U` domiciliación (exige IBAN), `G` ingreso en cuenta
corriente tributaria y `N` negativa; por defecto `I` si hay importe a ingresar y `N` si no. Si hay importe a deducir
(casilla 04) es complementaria y pide el justificante de 13 dígitos de la declaración anterior. `validate()` rechaza
NIF inválido, casillas o resultado negativos, tipo `U` sin IBAN, tipo `N` con importe y complementaria sin
justificante; devuelve los errores ya traducidos. Nombre del archivo: `NIF_AAAA_PT.115`. Solo trimestres.

**Modelo 180** (`Lib/Modelo180File`, orden de 20-11-2000 con sus modificaciones, vigente desde 2023): un registro
tipo 1 (declarante, número identificativo, totales) y un registro tipo 2 por perceptor e inmueble. Todos de 500
posiciones, separados por CRLF. Se lanza `LengthException` si un registro no mide 500. El justificante por defecto es
`180` + año + `000001`; se puede marcar complementaria (`C`) o sustitutiva (`S`) con el número de la anterior.
Teléfono y contacto salen de la empresa si no se indican. El archivo se descarga como `modelo180_AAAA.txt`. La pantalla no lo genera si hay avisos de datos obligatorios
(`Modelo180::blockingWarnings()`): el diseño exige NIF y provincia del perceptor y, salvo en la situación 4, los datos
del inmueble.

## Por qué

- Los ficheros se añadieron en `f17a63a` (2026-11-26) para presentar sin teclear a mano. Los diseños de registro
  vienen citados en los docblocks; el historial no explica más.
- La longitud se comprueba en cada registro para detectar desajustes de posiciones al cambiar un campo.

## Al cambiarlo

- Cambiar un campo exige mantener el comentario con sus posiciones y que `strlen` siga en 500 (328 en la cabecera
  del 115). Pruébalo con `Test/main/Modelo115FileTest.php` y `Modelo180Test.php`.
- La acción `download` de la pantalla no genera el fichero si `validate()` devuelve errores.
