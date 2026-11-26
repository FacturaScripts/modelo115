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
use FacturaScripts\Dinamic\Lib\Modelo180;

/**
 * Construye la respuesta del endpoint de solo lectura GET /api/3/modelo180 (resumen anual de retenciones sobre
 * arrendamientos de inmuebles urbanos) a partir del cálculo anual de la Lib Modelo115 y de los registros de la
 * Lib Modelo180, los mismos que usan la pantalla y el fichero del modelo 180.
 *
 * Devuelve el resumen de la declaración, las casillas 01-03 del resumen y siempre el detalle por arrendador e
 * inmueble (referencia catastral y situación), con los mismos registros que el fichero. Es una declaración
 * informativa: resultado vale null.
 *
 * Parámetros: codejercicio, idempresa, anio, periodo (opcional; solo ANUAL, el valor por defecto), detalle
 * (añade las facturas) y codretencion (retención por la que filtrar las facturas; vacío = todas).
 *
 * No crea, modifica ni borra registros.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo180Api
{
    use Modelo115ApiTrait;

    /** Modelo de la AEAT. */
    public const MODEL = '180';

    /** Periodos admitidos por el endpoint, ya normalizados. */
    public const PERIODS = ['ANUAL'];

    /**
     * Calcula el modelo 180 con los parámetros de la petición y devuelve el código HTTP y el cuerpo de la
     * respuesta.
     *
     * @param array $params parámetros de la query string: codejercicio, idempresa, anio, periodo, detalle y
     *                      codretencion
     *
     * @return array ['code' => código HTTP, 'body' => datos del modelo o error con status, message y extras]
     */
    public static function build(array $params): array
    {
        // periodo: solo anual, y si no se indica se usa el anual
        $rawPeriod = trim((string)($params['periodo'] ?? ''));
        $period = $rawPeriod === '' ? 'ANUAL' : static::normalizePeriod($rawPeriod);
        if (false === in_array($period, static::PERIODS, true)) {
            return static::error(400, 'invalid-period', ['allowed' => static::PERIODS]);
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

        // calculamos el año completo y sus registros con las mismas Lib que la pantalla y el fichero
        $result = Modelo115::generate($exercise->codejercicio, 'ANNUAL', $codretencion);
        $result180 = Modelo180::build($result['invoices']);

        $numRecords = (int)$result180['numrecords'];
        $base = static::money($result180['taxbase']);
        $retentions = static::money($result180['retentions']);

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
            ],
            'resumen' => [
                'num_registros' => $numRecords,
                'base' => $base,
                'retenciones' => $retentions,
                'num_facturas' => count($result['invoices']),
            ],
            // casillas del resumen de la declaración: 01 número de perceptores, 02 base de las retenciones y
            // 03 retenciones (los mismos totales del registro del declarante del fichero)
            'casillas' => [
                '01' => $numRecords,
                '02' => $base,
                '03' => $retentions,
            ],
            'resultado' => null,
            // el detalle por arrendador e inmueble va siempre: es lo que declara el modelo 180
            'detalle' => [
                'perceptores' => static::getRecords($result180),
            ],
        ];

        if (static::parseBool($params['detalle'] ?? '')) {
            $data['detalle']['facturas'] = static::getInvoices($result);
        }

        $data['avisos'] = static::getWarnings($exercise, $result, $result180);
        return ['code' => 200, 'body' => $data];
    }

    /**
     * Devuelve los registros de perceptor del modelo 180 (uno por arrendador e inmueble), con los mismos
     * datos que el fichero de presentación.
     *
     * @param array $result180 resultados de Modelo180::build()
     *
     * @return array
     */
    protected static function getRecords(array $result180): array
    {
        $list = [];
        foreach ($result180['records'] as $record) {
            $property = null;
            if (!empty($record['idinmueble'])) {
                $property = [
                    'idinmueble' => (int)$record['idinmueble'],
                    'descripcion' => $record['inmueble'],
                    'situacion' => (int)$record['situacion'],
                    'refcatastral' => $record['refcatastral'],
                    'tipovia' => $record['tipovia'],
                    'nombrevia' => $record['nombrevia'],
                    'tiponumeracion' => $record['tiponumeracion'],
                    'numero' => $record['numero'],
                    'calificador' => $record['calificador'],
                    'bloque' => $record['bloque'],
                    'portal' => $record['portal'],
                    'escalera' => $record['escalera'],
                    'planta' => $record['planta'],
                    'puerta' => $record['puerta'],
                    'complemento' => $record['complemento'],
                    'localidad' => $record['localidad'],
                    'municipio' => $record['municipio'],
                    'codmunicipio' => $record['codmunicipio'],
                    'codprovincia' => $record['provinciainmueble'],
                    'codpostal' => $record['codpostal'],
                ];
            }

            $list[] = [
                'codproveedor' => $record['codproveedor'],
                'nombre' => $record['nombre'],
                'cifnif' => $record['cifnif'],
                'codprovincia' => $record['codprovincia'],
                'modalidad' => (int)$record['modalidad'],
                'base' => static::money($record['base']),
                'porcentaje' => static::money($record['porcentaje']),
                'retenciones' => static::money($record['retenciones']),
                'ejercicio_devengo' => (int)$record['ejerciciodevengo'],
                'situacion' => (int)$record['situacion'],
                'inmueble' => $property,
                'reparto' => (bool)$record['reparto'],
                'num_facturas' => (int)$record['numfacturas'],
                'facturas' => $record['facturas'],
            ];
        }
        return $list;
    }
}
