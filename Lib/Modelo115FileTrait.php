<?php
/**
 * This file is part of Modelo115 plugin for FacturaScripts
 * Copyright (C) 2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Plugins\Modelo115\Lib;

use FacturaScripts\Core\Tools;

/**
 * Formatea los campos de los ficheros de presentación de la AEAT del plugin, el del modelo 115 (Modelo115File) y
 * el del modelo 180 (Modelo180File): textos en mayúsculas, sin tildes ni caracteres especiales (se conservan la
 * Ñ y la Ç), ajustados a la izquierda y rellenos de blancos, ya en ISO-8859-1; numéricos a la derecha y con ceros
 * por la izquierda; importes en céntimos sin coma decimal.
 *
 * Es un trait y no una clase para que no se despliegue en Dinamic/Lib, que es común a todos los plugins.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
trait Modelo115FileTrait
{
    /**
     * Campo alfanumérico: mayúsculas, sin tildes ni caracteres especiales, ajustado a la izquierda y
     * rellenado con blancos. Se pasa a ISO-8859-1 antes de rellenar para que la Ñ y la Ç ocupen un byte.
     *
     * @param string|null $value
     * @param int $length
     *
     * @return string texto en ISO-8859-1 de la longitud indicada
     */
    public static function formatAlpha(?string $value, int $length): string
    {
        $text = mb_convert_encoding(static::sanitize((string)$value), 'ISO-8859-1', 'UTF-8');
        return str_pad(substr($text, 0, $length), $length, ' ', STR_PAD_RIGHT);
    }

    /**
     * Importe sin signo y sin coma decimal (en céntimos), ajustado a la derecha y con ceros a la izquierda.
     *
     * @param float $amount
     * @param int $length
     *
     * @return string
     */
    public static function formatAmount(float $amount, int $length): string
    {
        return static::formatNumber((string)(int)round(abs($amount) * 100), $length);
    }

    /**
     * NIF ajustado a la derecha y con ceros por la izquierda, sin espacios ni guiones.
     *
     * @param string|null $nif
     *
     * @return string
     */
    public static function formatNif(?string $nif): string
    {
        $value = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', Tools::fixHtml((string)$nif)));
        if ($value === '') {
            return str_repeat(' ', 9);
        }

        return str_pad(substr($value, -9), 9, '0', STR_PAD_LEFT);
    }

    /**
     * Campo numérico: solo dígitos, ajustado a la derecha y con ceros por la izquierda. Si es más largo,
     * se queda con las últimas cifras.
     *
     * @param string|int|null $value
     * @param int $length
     *
     * @return string
     */
    public static function formatNumber($value, int $length): string
    {
        $digits = preg_replace('/\D/', '', (string)$value);
        return str_pad(substr($digits, -$length), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Porcentaje con 2 enteros y 2 decimales, sin coma (19 % = 1900).
     *
     * @param float $percentage
     *
     * @return string
     */
    public static function formatPercentage(float $percentage): string
    {
        return static::formatNumber((string)(int)round(abs($percentage) * 100), 4);
    }

    /**
     * Pasa un texto a mayúsculas, sin tildes ni caracteres especiales. Conserva la Ñ y la Ç, y los signos
     * que se usan en direcciones y razones sociales (punto, coma, guion, barra, apóstrofo, paréntesis y
     * ampersand); el resto, como ª, º o €, se cambia por un espacio.
     *
     * @param string $text texto en UTF-8 (puede traer entidades HTML)
     *
     * @return string texto en UTF-8
     */
    public static function sanitize(string $text): string
    {
        $text = mb_strtoupper(Tools::fixHtml($text) ?? '', 'UTF-8');
        $text = strtr($text, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y', 'Ÿ' => 'Y',
        ]);
        $text = preg_replace("/[^A-Z0-9ÑÇ .,\\-\\/'()&]/u", ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
