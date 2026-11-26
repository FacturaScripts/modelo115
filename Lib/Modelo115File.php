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
use FacturaScripts\Dinamic\Model\Empresa;
use LengthException;

/**
 * Genera el fichero de presentación del modelo 115 (autoliquidación trimestral de retenciones sobre
 * arrendamientos de inmuebles urbanos) según el diseño de registro de la AEAT DR115e15v13 (Orden EHA/3435/2007,
 * «Ejercicios 2019 y siguientes», versión 1.3): un único registro con la cabecera `<T1150AAAAPP0000><AUX>`
 * (328 posiciones), la página 01 (500 posiciones) y el cierre `</T1150AAAAPP0000>`, en ISO-8859-1.
 *
 * Las casillas salen de Modelo115::generate(): 01 perceptores, 02 base, 03 retenciones, 04 resultados de
 * autoliquidaciones anteriores del mismo periodo y 05 resultado a ingresar (03 - 04). En el diseño ninguna
 * casilla lleva signo: el fichero no admite importes negativos.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo115File
{
    use Modelo115FileTrait;

    /** Longitud de la cabecera <T1150...><AUX>...</AUX>. */
    public const HEADER_LENGTH = 328;

    /** Longitud de la página 01. */
    public const PAGE_LENGTH = 500;

    /** Tipos de declaración: ingreso, domiciliación, ingreso a anotar en la cuenta corriente tributaria y negativa. */
    public const TYPES = ['I', 'U', 'G', 'N'];

    /**
     * Genera el contenido del fichero.
     *
     * @param array $result resultados de Modelo115::generate() de un trimestre
     * @param Empresa $empresa empresa declarante
     * @param array $options tipo (I, U, G o N; por defecto I si hay importe a ingresar y N si no), iban
     *                       (domiciliación) y justificante (13 dígitos de la declaración anterior, para las
     *                       complementarias, que son las que tienen importes a deducir)
     *
     * @return string contenido en ISO-8859-1
     */
    public static function generate(array $result, Empresa $empresa, array $options): string
    {
        $year = date('Y', strtotime($result['exercise']->fechainicio));
        $id = '1150' . $year . static::periodCode($result['period']) . '0000>';

        $header = '<T' . $id . '<AUX>'
            . str_repeat(' ', 70)        // reservado para la Administración
            . str_repeat(' ', 4)         // versión del programa
            . str_repeat(' ', 4)         // reservado para la Administración
            . str_repeat(' ', 9)         // NIF de la empresa desarrolladora
            . str_repeat(' ', 213)       // reservado para la Administración
            . '</AUX>';
        if (strlen($header) !== static::HEADER_LENGTH) {
            throw new LengthException('Modelo 115: cabecera de ' . strlen($header) . ' posiciones');
        }

        return $header . static::page01($result, $empresa, $options, $year) . '</T' . $id;
    }

    /**
     * Devuelve el código del periodo del fichero (1T a 4T).
     *
     * @param string $period periodo del modelo (T1 a T4)
     *
     * @return string
     */
    public static function periodCode(string $period): string
    {
        return match ($period) {
            'T2' => '2T',
            'T3' => '3T',
            'T4' => '4T',
            default => '1T',
        };
    }

    /**
     * Devuelve el tipo de declaración: el indicado o, por defecto, I (ingreso) si hay importe a ingresar y N
     * (negativa) si no.
     *
     * @param array $result resultados del modelo
     * @param array $options opciones del fichero
     *
     * @return string
     */
    public static function type(array $result, array $options): string
    {
        $type = strtoupper(trim((string)($options['tipo'] ?? '')));
        if (in_array($type, static::TYPES, true)) {
            return $type;
        }
        return round($result['result'], 2) > 0.0 ? 'I' : 'N';
    }

    /**
     * Comprueba los datos antes de generar el fichero.
     *
     * @param array $result resultados de Modelo115::generate()
     * @param Empresa $empresa empresa declarante
     * @param array $options opciones del fichero (tipo, iban y justificante)
     *
     * @return string[] errores encontrados, ya traducidos (vacío si se puede generar el fichero)
     */
    public static function validate(array $result, Empresa $empresa, array $options): array
    {
        $errors = [];
        if (false === in_array($result['period'] ?? '', ['T1', 'T2', 'T3', 'T4'], true)) {
            $errors[] = Tools::trans('m115-file-not-for-model-180');
            return $errors;
        }

        $nif = static::nif($empresa->cifnif);
        if ($nif === '' || strlen($nif) > 9) {
            $errors[] = Tools::trans('m115-invalid-company-nif', ['%nif%' => (string)$empresa->cifnif]);
        }

        if (round($result['taxbase'], 2) < 0.0 || round($result['retentions'], 2) < 0.0
            || round($result['todeduct'], 2) < 0.0) {
            $errors[] = Tools::trans('m115-negative-box');
        }
        if (round($result['result'], 2) < 0.0) {
            $errors[] = Tools::trans('m115-negative-result');
        }

        $type = static::type($result, $options);
        if ($type === 'U' && strlen(static::iban($options)) < 15) {
            $errors[] = Tools::trans('m115-missing-iban');
        }
        if ($type === 'N' && round($result['result'], 2) > 0.0) {
            $errors[] = Tools::trans('m115-negative-type-with-amount');
        }
        if (round($result['todeduct'], 2) != 0.0
            && 1 !== preg_match('/^\d{13}$/', (string)($options['justificante'] ?? ''))) {
            $errors[] = Tools::trans('m115-missing-previous-receipt');
        }

        return $errors;
    }

    /**
     * Campo alfanumérico de la página 01: el diseño solo admite letras, números y blancos, así que quita los
     * puntos y cambia por blancos el resto de signos que conserva sanitize().
     *
     * @param string|null $value valor
     * @param int $length longitud del campo
     *
     * @return string texto en ISO-8859-1 de la longitud indicada
     */
    protected static function alpha(?string $value, int $length): string
    {
        $text = str_replace('.', '', static::sanitize((string)$value));
        $text = trim(preg_replace('/ {2,}/', ' ', preg_replace('/[^A-Z0-9ÑÇ ]/u', ' ', $text)));
        return static::formatAlpha($text, $length);
    }

    /**
     * Devuelve el IBAN de las opciones, en mayúsculas y sin espacios.
     *
     * @param array $options opciones del fichero
     *
     * @return string
     */
    protected static function iban(array $options): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($options['iban'] ?? '')));
    }

    /**
     * Devuelve el NIF en mayúsculas, sin espacios ni signos y sin el prefijo ES de los NIF-IVA.
     *
     * @param string|null $nif NIF
     *
     * @return string
     */
    protected static function nif(?string $nif): string
    {
        $value = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', Tools::fixHtml((string)$nif) ?? ''));
        if (strlen($value) > 9 && str_starts_with($value, 'ES')) {
            $value = substr($value, 2);
        }
        return $value;
    }

    /**
     * Página 01 de la declaración.
     *
     * @param array $result resultados del modelo
     * @param Empresa $empresa empresa declarante
     * @param array $options opciones del fichero
     * @param string $year ejercicio (AAAA)
     *
     * @return string página en ISO-8859-1
     */
    protected static function page01(array $result, Empresa $empresa, array $options, string $year): string
    {
        $complementaria = round($result['todeduct'], 2) != 0.0;

        $page = '<T11501000>'                                                       // 1-11 inicio de la página
            . ' '                                                                   // 12 página complementaria
            . static::type($result, $options)                                       // 13 tipo de declaración
            . static::formatAlpha(static::nif($empresa->cifnif), 9)                 // 14-22 NIF
            . static::alpha($empresa->nombre, 60)                                   // 23-82 razón social o apellidos
            . str_repeat(' ', 20)                                                   // 83-102 nombre
            . $year                                                                 // 103-106 ejercicio
            . static::periodCode($result['period'])                                 // 107-108 periodo
            . static::formatNumber((int)$result['numrecipients'], 15)               // 109-123 [01] perceptores
            . static::formatAmount($result['taxbase'], 17)                          // 124-140 [02] base
            . static::formatAmount($result['retentions'], 17)                       // 141-157 [03] retenciones
            . static::formatAmount($result['todeduct'], 17)                         // 158-174 [04] anteriores
            . static::formatAmount($result['result'], 17)                           // 175-191 [05] a ingresar
            . ($complementaria ? 'X' : ' ')                                         // 192 complementaria
            . static::formatAlpha($complementaria ? ($options['justificante'] ?? '') : '', 13) // 193-205 justificante
            . static::formatAlpha(static::iban($options), 34)                       // 206-239 IBAN
            . str_repeat(' ', 236)                                                  // 240-475 reservado
            . str_repeat(' ', 13)                                                   // 476-488 sello electrónico
            . '</T11501000>';                                                       // 489-500 fin de la página

        if (strlen($page) !== static::PAGE_LENGTH) {
            throw new LengthException('Modelo 115: página 01 de ' . strlen($page) . ' posiciones');
        }
        return $page;
    }
}
