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

use FacturaScripts\Dinamic\Lib\Modelo115;

/**
 * Construye la respuesta del endpoint de solo lectura GET /api/3/modelo115 a partir del cálculo de la
 * Lib Modelo115, la misma que usa la pantalla del plugin. Solo trimestres: el resumen anual (modelo 180) tiene
 * su propio endpoint, GET /api/3/modelo180 (Modelo180Api).
 *
 * Parámetros comunes: codejercicio, idempresa, anio, periodo (T1-T4, obligatorio) y detalle.
 * Parámetros propios: codretencion (retención por la que filtrar las facturas; vacío = todas) y
 * a_deducir (importe de autoliquidaciones anteriores del mismo periodo, positivo o 0; 0 por defecto).
 *
 * No crea, modifica ni borra registros.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo115Api
{
    use Modelo115ApiTrait;

    /** Modelo de la AEAT. */
    public const MODEL = '115';

    /** Periodos admitidos por el endpoint, ya normalizados. */
    public const PERIODS = ['T1', 'T2', 'T3', 'T4'];

    /**
     * Calcula el modelo 115 con los parámetros de la petición y devuelve el código HTTP y el cuerpo de la
     * respuesta.
     *
     * @param array $params parámetros de la query string: codejercicio, idempresa, anio, periodo, detalle,
     *                      codretencion y a_deducir
     *
     * @return array ['code' => código HTTP, 'body' => datos del modelo o error con status, message y extras]
     */
    public static function build(array $params): array
    {
        // comprobamos el periodo
        $rawPeriod = trim((string)($params['periodo'] ?? ''));
        if ($rawPeriod === '') {
            return static::error(400, 'missing-period', ['allowed' => static::PERIODS]);
        }
        $period = static::normalizePeriod($rawPeriod);
        if (false === in_array($period, static::PERIODS, true)) {
            return static::error(400, 'invalid-period', ['allowed' => static::PERIODS]);
        }

        // comprobamos los parámetros propios
        $toDeduct = static::parseFloat($params['a_deducir'] ?? null);
        if (null === $toDeduct || $toDeduct < 0) {
            // la casilla 04 es lo ya ingresado en autoliquidaciones anteriores y se resta: no admite negativos
            return static::error(400, 'invalid-parameter', ['parameter' => 'a_deducir']);
        }

        $retention = static::checkRetention($params);
        if (isset($retention['error'])) {
            return $retention['error'];
        }
        $codretencion = $retention['codretencion'];

        // buscamos el ejercicio y su empresa
        $resolved = static::resolveExercise($params);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }
        $exercise = $resolved['exercise'];

        // calculamos el modelo con la misma Lib que la pantalla
        $result = Modelo115::generate($exercise->codejercicio, $period, $codretencion, $toDeduct);

        $data = [
            'modelo' => static::MODEL,
            'plugin_version' => static::pluginVersion(),
            'empresa' => static::companyData($resolved['company']),
            'ejercicio' => static::exerciseData($exercise),
            'periodo' => $period,
            'fechainicio' => static::isoDate($result['dateStart']),
            'fechafin' => static::isoDate($result['dateEnd']),
            'parametros' => [
                'codretencion' => $codretencion,
                'a_deducir' => static::money($toDeduct),
            ],
            'resumen' => [
                'perceptores' => (int)$result['numrecipients'],
                'base' => static::money($result['taxbase']),
                'retenciones' => static::money($result['retentions']),
                'a_deducir' => static::money($result['todeduct']),
                'resultado' => static::money($result['result']),
                'num_facturas' => count($result['invoices']),
                'total_neto' => static::money($result['totalNeto']),
                'total_iva' => static::money($result['totalIva']),
                'total_recargo' => static::money($result['totalRecargo']),
                'total_irpf' => static::money($result['totalIrpf']),
                'total' => static::money($result['totalTotal']),
            ],
            // casillas oficiales del modelo 115: 01 perceptores, 02 base, 03 retenciones, 04 resultados de
            // autoliquidaciones anteriores y 05 resultado a ingresar (03 - 04)
            'casillas' => [
                '01' => (int)$result['numrecipients'],
                '02' => static::money($result['taxbase']),
                '03' => static::money($result['retentions']),
                '04' => static::money($result['todeduct']),
                '05' => static::money($result['result']),
            ],
            'resultado' => static::money($result['result']),
        ];

        if (static::parseBool($params['detalle'] ?? '')) {
            $data['detalle'] = [
                'facturas' => static::getInvoices($result),
                'perceptores' => static::getRecipients($result),
            ];
        }

        $data['avisos'] = static::getWarnings($exercise, $result);
        return ['code' => 200, 'body' => $data];
    }

    /**
     * Devuelve el detalle por perceptor (proveedor) de los trimestres: base, retenciones y número de
     * facturas, ordenado por código de proveedor.
     *
     * @param array $result resultados de Modelo115::generate()
     *
     * @return array
     */
    protected static function getRecipients(array $result): array
    {
        $recipients = [];
        foreach ($result['invoices'] as $invoice) {
            $code = (string)$invoice->codproveedor;
            if (false === isset($recipients[$code])) {
                $recipients[$code] = [
                    'codproveedor' => $code,
                    'nombre' => (string)$invoice->nombre,
                    'cifnif' => (string)$invoice->cifnif,
                    'base' => 0.0,
                    'retenciones' => 0.0,
                    'num_facturas' => 0,
                ];
            }

            $recipients[$code]['base'] += (float)$invoice->neto;
            $recipients[$code]['retenciones'] += (float)$invoice->totalirpf;
            $recipients[$code]['num_facturas']++;
        }
        ksort($recipients, SORT_STRING);

        $list = [];
        foreach ($recipients as $recipient) {
            $recipient['base'] = static::money($recipient['base']);
            $recipient['retenciones'] = static::money($recipient['retenciones']);
            $list[] = $recipient;
        }
        return $list;
    }
}
