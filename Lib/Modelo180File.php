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

/**
 * Genera el fichero de presentación del modelo 180 según el diseño de registro de la AEAT (Orden de
 * 20-11-2000, anexo modificado por las órdenes HAP/1732/2014, HAC/1276/2020, HFP/1351/2021 y HFP/1284/2023;
 * vigente desde el ejercicio 2023).
 *
 * El fichero tiene un registro de tipo 1 (declarante y totales) y un registro de tipo 2 por cada perceptor
 * e inmueble, todos de 500 posiciones, en ISO-8859-1 y separados por CRLF. Los textos van en mayúsculas,
 * sin tildes ni caracteres especiales (se conservan la Ñ y la Ç); los numéricos, a la derecha y con ceros
 * por la izquierda; los importes, en céntimos sin coma decimal.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo180File
{
    use Modelo115FileTrait;

    /** Separador de registros. */
    const EOL = "\r\n";

    /** Longitud de todos los registros. */
    const RECORD_LENGTH = 500;

    /**
     * Número identificativo por defecto de la declaración: 180 + año + 000001.
     *
     * @param int $year
     *
     * @return string
     */
    public static function defaultReceipt(int $year): string
    {
        return '180' . sprintf('%04d', $year) . '000001';
    }

    /**
     * Genera el contenido del fichero, ya codificado en ISO-8859-1.
     *
     * @param Empresa $company empresa declarante
     * @param int $year ejercicio declarado
     * @param array $records registros de perceptor de Modelo180::build()
     * @param array $options datos del registro de tipo 1: justificante (número identificativo de 13
     *                       dígitos, por defecto 180 + año + 000001), tipo ('' normal, 'C' complementaria o
     *                       'S' sustitutiva), anterior (número de la declaración anterior), telefono y
     *                       contacto (persona con quién relacionarse; por defecto, los de la empresa)
     *
     * @return string
     */
    public static function generate(Empresa $company, int $year, array $records, array $options = []): string
    {
        $lines = [static::getDeclarantRecord($company, $year, $records, $options)];
        foreach ($records as $record) {
            $lines[] = static::getRecipientRecord($company, $year, $record);
        }

        return implode(static::EOL, $lines) . static::EOL;
    }

    /**
     * Comprueba que el registro mide exactamente 500 posiciones (bytes en ISO-8859-1).
     *
     * @param string $line
     *
     * @return string
     */
    protected static function checkLength(string $line): string
    {
        if (strlen($line) !== static::RECORD_LENGTH) {
            throw new \LengthException('Registro del modelo 180 de ' . strlen($line) . ' posiciones en lugar de ' . static::RECORD_LENGTH);
        }

        return $line;
    }

    /**
     * Registro de tipo 1: declarante, número de la declaración y totales.
     *
     * @param Empresa $company
     * @param int $year
     * @param array $records
     * @param array $options
     *
     * @return string
     */
    protected static function getDeclarantRecord(Empresa $company, int $year, array $records, array $options): string
    {
        $taxbase = 0.0;
        $retentions = 0.0;
        foreach ($records as $record) {
            $taxbase += (float)$record['base'];
            $retentions += (float)$record['retenciones'];
        }

        $type = strtoupper((string)($options['tipo'] ?? ''));
        $receipt = (string)($options['justificante'] ?? '');
        $phone = (string)($options['telefono'] ?? $company->telefono1);
        $contact = (string)($options['contacto'] ?? $company->administrador);

        $line = '1' // tipo de registro
            . '180' // modelo
            . static::formatNumber($year, 4) // ejercicio
            . static::formatNif($company->cifnif) // NIF del declarante
            . static::formatAlpha($company->nombre, 40) // razón social del declarante
            . 'T' // tipo de soporte: telemático
            . static::formatNumber($phone, 9) // teléfono de contacto
            . static::formatAlpha($contact, 40) // persona con quién relacionarse
            . static::formatNumber($receipt === '' ? static::defaultReceipt($year) : $receipt, 13) // número identificativo
            . ($type === 'C' ? 'C' : ' ') // declaración complementaria
            . ($type === 'S' ? 'S' : ' ') // declaración sustitutiva
            . static::formatNumber(in_array($type, ['C', 'S'], true) ? ($options['anterior'] ?? '') : '', 13) // declaración anterior
            . static::formatNumber(count($records), 9) // número total de perceptores (registros de tipo 2)
            . (round($taxbase, 2) < 0 ? 'N' : ' ') // signo de la suma de bases
            . static::formatAmount($taxbase, 15) // suma de bases
            . static::formatAmount($retentions, 15) // suma de retenciones
            . str_repeat(' ', 62) // blancos
            . str_repeat(' ', 263); // sello electrónico: en blanco salvo en los programas de la AEAT

        return static::checkLength($line);
    }

    /**
     * Registro de tipo 2: perceptor (arrendador) e inmueble.
     *
     * @param Empresa $company
     * @param int $year
     * @param array $record registro de Modelo180::build()
     *
     * @return string
     */
    protected static function getRecipientRecord(Empresa $company, int $year, array $record): string
    {
        $situation = (int)$record['situacion'];
        $line = '2' // tipo de registro
            . '180' // modelo
            . static::formatNumber($year, 4) // ejercicio
            . static::formatNif($company->cifnif) // NIF del declarante
            . static::formatNif($record['cifnif']) // NIF del perceptor
            . str_repeat(' ', 9) // NIF del representante legal: solo para menores de 14 años
            . static::formatAlpha($record['nombre'], 40) // apellidos y nombre o razón social del perceptor
            . static::formatNumber($record['codprovincia'], 2) // provincia del domicilio del perceptor
            . static::formatNumber($record['modalidad'], 1) // modalidad: 1 dineraria
            . ((float)$record['base'] < 0 ? 'N' : ' ') // signo de la base
            . static::formatAmount((float)$record['base'], 13) // base de las retenciones
            . static::formatPercentage((float)$record['porcentaje']) // % de retención
            . static::formatAmount((float)$record['retenciones'], 13) // retenciones
            . static::formatNumber($record['ejerciciodevengo'], 4) // ejercicio de devengo (0000 = el declarado)
            . static::formatNumber($situation, 1) // situación del inmueble
            . static::formatAlpha($situation === 4 ? '' : $record['refcatastral'], 20) // referencia catastral
            . static::formatAlpha($record['tipovia'], 5) // tipo de vía
            . static::formatAlpha($record['nombrevia'], 50) // nombre de la vía pública
            . static::formatAlpha($record['tiponumeracion'], 3) // tipo de numeración
            . static::formatAlpha($record['numero'], 5) // número de casa
            . static::formatAlpha($record['calificador'], 3) // calificador del número
            . static::formatAlpha($record['bloque'], 3) // bloque
            . static::formatAlpha($record['portal'], 3) // portal
            . static::formatAlpha($record['escalera'], 3) // escalera
            . static::formatAlpha($record['planta'], 3) // planta o piso
            . static::formatAlpha($record['puerta'], 3) // puerta
            . static::formatAlpha($record['complemento'], 40) // complemento
            . static::formatAlpha($record['localidad'], 30) // localidad o población
            . static::formatAlpha($record['municipio'], 30) // municipio
            . static::formatAlpha($record['codmunicipio'], 5) // código de municipio
            . static::formatAlpha($record['provinciainmueble'], 2) // código de provincia del inmueble
            . static::formatAlpha($record['codpostal'], 5) // código postal
            . str_repeat(' ', 173); // blancos

        return static::checkLength($line);
    }
}
