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

use FacturaScripts\Core\DataSrc\Paises;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Inmueble180;
use FacturaScripts\Dinamic\Model\Proveedor;

/**
 * Prepara los registros de perceptor del modelo 180 (resumen anual de retenciones de arrendamientos) a
 * partir de las facturas de proveedor del año que calcula la Lib Modelo115.
 *
 * Genera un registro por arrendador (proveedor) e inmueble. Cada factura va a su inmueble si lo indica
 * (columna idinmueble180); si no, al único inmueble del proveedor o, si tiene varios, se reparte entre ellos
 * según el porcentaje de cada uno (a partes iguales si ninguno lo tiene). Los proveedores sin inmuebles
 * generan un registro sin referencia catastral (situación 4).
 *
 * Los avisos se devuelven como claves de traducción con sus parámetros, para que la pantalla y la API los
 * traduzcan.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo180
{
    /** Modalidad de la percepción: dineraria. */
    const MODALIDAD_DINERARIA = 1;

    /** Códigos de provincia del modelo 180 con los nombres por los que se reconocen (sin tildes). */
    const PROVINCES = [
        '01' => ['alava', 'araba', 'araba/alava', 'alava/araba'],
        '02' => ['albacete'],
        '03' => ['alicante', 'alacant', 'alicante/alacant'],
        '04' => ['almeria'],
        '05' => ['avila'],
        '06' => ['badajoz'],
        '07' => ['baleares', 'illes balears', 'islas baleares', 'balears', 'illes baleares'],
        '08' => ['barcelona'],
        '09' => ['burgos'],
        '10' => ['caceres'],
        '11' => ['cadiz'],
        '12' => ['castellon', 'castello', 'castellon/castello'],
        '13' => ['ciudad real'],
        '14' => ['cordoba'],
        '15' => ['a coruna', 'coruna', 'la coruna', 'coruna, a'],
        '16' => ['cuenca'],
        '17' => ['girona', 'gerona'],
        '18' => ['granada'],
        '19' => ['guadalajara'],
        '20' => ['gipuzkoa', 'guipuzcoa'],
        '21' => ['huelva'],
        '22' => ['huesca'],
        '23' => ['jaen'],
        '24' => ['leon'],
        '25' => ['lleida', 'lerida'],
        '26' => ['la rioja', 'rioja', 'rioja, la'],
        '27' => ['lugo'],
        '28' => ['madrid'],
        '29' => ['malaga'],
        '30' => ['murcia'],
        '31' => ['navarra', 'nafarroa'],
        '32' => ['ourense', 'orense'],
        '33' => ['asturias'],
        '34' => ['palencia'],
        '35' => ['las palmas', 'palmas', 'palmas, las'],
        '36' => ['pontevedra'],
        '37' => ['salamanca'],
        '38' => ['santa cruz de tenerife', 's.c.tenerife', 'tenerife'],
        '39' => ['cantabria'],
        '40' => ['segovia'],
        '41' => ['sevilla'],
        '42' => ['soria'],
        '43' => ['tarragona'],
        '44' => ['teruel'],
        '45' => ['toledo'],
        '46' => ['valencia', 'valencia/valencia'],
        '47' => ['valladolid'],
        '48' => ['bizkaia', 'vizcaya'],
        '49' => ['zamora'],
        '50' => ['zaragoza'],
        '51' => ['ceuta'],
        '52' => ['melilla'],
        '53' => ['isla de la palma', 'la palma'],
    ];

    /** @var array inmuebles por proveedor, indexados por id */
    protected static $properties = [];

    /** @var array registros por clave proveedor|inmueble */
    protected static $records = [];

    /** @var array proveedores ya cargados */
    protected static $suppliers = [];

    /** @var array avisos: cada uno con su clave de traducción (key) y parámetros (params), sin repetir */
    protected static $warnings = [];

    /**
     * Agrupa las facturas por arrendador e inmueble y devuelve los registros de perceptor del 180.
     *
     * @param FacturaProveedor[] $invoices facturas de proveedor del año (Modelo115::generate() del periodo anual)
     *
     * @return array con records (registros de perceptor), warnings (avisos), numrecords (número de
     *               registros, que es el número de perceptores del 180), taxbase (suma de bases) y
     *               retentions (suma de retenciones)
     */
    public static function build(array $invoices): array
    {
        static::$properties = [];
        static::$records = [];
        static::$suppliers = [];
        static::$warnings = [];

        foreach ($invoices as $invoice) {
            static::addInvoice($invoice);
        }

        // redondeamos y ordenamos por proveedor e inmueble
        ksort(static::$records, SORT_STRING);
        $records = [];
        $taxbase = 0.0;
        $retentions = 0.0;
        foreach (static::$records as $record) {
            $record['base'] = round($record['base'], 2) + 0.0;
            $record['retenciones'] = round($record['retenciones'], 2) + 0.0;
            if ($record['base'] < 0) {
                static::addWarning('model-180-negative-base', ['%name%' => $record['nombre']]);
            }

            $taxbase += $record['base'];
            $retentions += $record['retenciones'];
            $records[] = $record;
        }

        return [
            'records' => $records,
            'warnings' => array_values(static::$warnings),
            'numrecords' => count($records),
            'taxbase' => round($taxbase, 2) + 0.0,
            'retentions' => round($retentions, 2) + 0.0,
        ];
    }

    /**
     * Indica si el código es uno de los códigos de provincia del modelo 180 (01 a 53).
     *
     * @param string $code código de dos dígitos
     *
     * @return bool
     */
    public static function isProvinceCode(string $code): bool
    {
        return isset(static::PROVINCES[$code]);
    }

    /**
     * Devuelve el código de provincia del modelo 180 a partir del código postal o, si no se puede, del
     * nombre de la provincia. Los códigos postales 387xx son de la Isla de La Palma (53).
     *
     * @param string|null $codpostal código postal
     * @param string|null $provincia nombre de la provincia
     *
     * @return string código de dos dígitos o vacío si no se reconoce
     */
    public static function provinceCode(?string $codpostal, ?string $provincia): string
    {
        $zip = preg_replace('/\D/', '', (string)$codpostal);
        if (strlen($zip) === 5) {
            if (str_starts_with($zip, '387')) {
                return '53';
            }

            $code = substr($zip, 0, 2);
            if ($code !== '53' && static::isProvinceCode($code)) {
                return $code;
            }
        }

        $name = static::normalizeName((string)$provincia);
        if ($name === '') {
            return '';
        }
        foreach (static::PROVINCES as $code => $names) {
            if (in_array($name, $names, true)) {
                return $code;
            }
        }
        return '';
    }

    /**
     * Suma una factura a los registros de su proveedor: a su inmueble, al único del proveedor o repartida
     * entre todos sus inmuebles.
     *
     * @param FacturaProveedor $invoice
     */
    protected static function addInvoice(FacturaProveedor $invoice): void
    {
        $code = (string)$invoice->codproveedor;
        $supplier = static::getSupplier($code);
        $properties = static::getProperties($code);
        $shares = static::getShares($invoice, $supplier, $properties);

        // repartimos la base y la retención; el último inmueble se queda con el resto del redondeo
        $base = (float)$invoice->neto;
        $retention = (float)$invoice->totalirpf;
        $pendingBase = $base;
        $pendingRetention = $retention;
        $count = count($shares);
        $num = 0;
        foreach ($shares as $idproperty => $share) {
            $num++;
            $shareBase = $num === $count ? $pendingBase : round($base * $share, 2);
            $shareRetention = $num === $count ? $pendingRetention : round($retention * $share, 2);
            $pendingBase -= $shareBase;
            $pendingRetention -= $shareRetention;

            $key = $code . '|' . sprintf('%010d', $idproperty);
            if (false === isset(static::$records[$key])) {
                static::$records[$key] = static::newRecord($supplier, $properties[$idproperty] ?? null);
            }

            static::$records[$key]['base'] += $shareBase;
            static::$records[$key]['retenciones'] += $shareRetention;
            static::$records[$key]['porcentaje'] = (float)$invoice->irpf;
            static::$records[$key]['numfacturas']++;
            static::$records[$key]['facturas'][] = (string)$invoice->codigo;
            if ($count > 1) {
                static::$records[$key]['reparto'] = true;
            }
        }
    }

    /**
     * Añade un aviso si no estaba ya.
     *
     * @param string $key clave de traducción
     * @param array $params parámetros del texto
     */
    protected static function addWarning(string $key, array $params = []): void
    {
        static::$warnings[$key . '|' . json_encode($params)] = ['key' => $key, 'params' => $params];
    }

    /**
     * Devuelve los inmuebles del proveedor indexados por id (con caché).
     *
     * @param string $codproveedor
     *
     * @return Inmueble180[]
     */
    protected static function getProperties(string $codproveedor): array
    {
        if (false === isset(static::$properties[$codproveedor])) {
            static::$properties[$codproveedor] = [];
            $where = [Where::eq('codproveedor', $codproveedor)];
            foreach (Inmueble180::all($where, ['id' => 'ASC'], 0, 0) as $property) {
                static::$properties[$codproveedor][(int)$property->id] = $property;
            }
        }

        return static::$properties[$codproveedor];
    }

    /**
     * Calcula a qué inmuebles va una factura y qué parte le corresponde a cada uno.
     *
     * @param FacturaProveedor $invoice
     * @param Proveedor $supplier
     * @param Inmueble180[] $properties inmuebles del proveedor indexados por id
     *
     * @return array fracciones (de 0 a 1) indexadas por id de inmueble (0 = sin inmueble)
     */
    protected static function getShares(FacturaProveedor $invoice, Proveedor $supplier, array $properties): array
    {
        // el inmueble indicado en la factura
        $idproperty = (int)($invoice->idinmueble180 ?? 0);
        if ($idproperty > 0 && isset($properties[$idproperty])) {
            return [$idproperty => 1.0];
        }
        if ($idproperty > 0) {
            static::addWarning('model-180-invoice-other-property', ['%code%' => $invoice->codigo]);
        }

        // sin inmuebles o con uno solo
        if (empty($properties)) {
            static::addWarning('model-180-supplier-without-property', ['%name%' => static::supplierName($supplier)]);
            return [0 => 1.0];
        }
        if (count($properties) === 1) {
            return [array_key_first($properties) => 1.0];
        }

        // varios inmuebles: reparto según su porcentaje o a partes iguales
        static::addWarning('model-180-split', [
            '%name%' => static::supplierName($supplier),
            '%count%' => count($properties),
        ]);
        $total = 0.0;
        foreach ($properties as $property) {
            $total += max(0.0, (float)$property->porcentaje);
        }

        $shares = [];
        foreach ($properties as $id => $property) {
            $shares[$id] = $total > 0 ? max(0.0, (float)$property->porcentaje) / $total : 1 / count($properties);
        }
        return $shares;
    }

    /**
     * Carga el proveedor (con caché) y comprueba sus datos de perceptor.
     *
     * @param string $codproveedor
     *
     * @return Proveedor
     */
    protected static function getSupplier(string $codproveedor): Proveedor
    {
        if (isset(static::$suppliers[$codproveedor])) {
            return static::$suppliers[$codproveedor];
        }

        $supplier = new Proveedor();
        $supplier->load($codproveedor);
        static::$suppliers[$codproveedor] = $supplier;

        $name = static::supplierName($supplier);
        if (empty($supplier->cifnif)) {
            static::addWarning('model-180-supplier-without-nif', ['%name%' => $name]);
        }

        $address = $supplier->getDefaultAddress();
        if (!empty($address->codpais) && strtoupper(Paises::get($address->codpais)->codiso ?? '') !== 'ES') {
            static::addWarning('model-180-foreign-supplier', ['%name%' => $name]);
        } elseif ('' === static::provinceCode($address->codpostal, $address->provincia)) {
            static::addWarning('model-180-supplier-without-province', ['%name%' => $name]);
        }

        return $supplier;
    }

    /**
     * Crea un registro de perceptor vacío para el proveedor y el inmueble.
     *
     * @param Proveedor $supplier
     * @param Inmueble180|null $property inmueble o null si el proveedor no tiene
     *
     * @return array
     */
    protected static function newRecord(Proveedor $supplier, ?Inmueble180 $property): array
    {
        $address = $supplier->getDefaultAddress();
        if ($property instanceof Inmueble180) {
            $missing = $property->missingData();
            if (!empty($missing)) {
                static::addWarning('model-180-property-missing-data', [
                    '%property%' => $property->primaryDescription(),
                    '%name%' => static::supplierName($supplier),
                    '%fields%' => implode(', ', array_map(static fn(string $key) => Tools::trans($key), $missing)),
                ]);
            }
        }

        return [
            'codproveedor' => (string)$supplier->codproveedor,
            'cifnif' => (string)$supplier->cifnif,
            'nombre' => static::supplierName($supplier),
            'codprovincia' => static::provinceCode($address->codpostal, $address->provincia),
            'modalidad' => static::MODALIDAD_DINERARIA,
            'base' => 0.0,
            'porcentaje' => 0.0,
            'retenciones' => 0.0,
            'ejerciciodevengo' => 0,
            'idinmueble' => $property ? (int)$property->id : null,
            'inmueble' => $property ? $property->primaryDescription() : '',
            'situacion' => $property ? (int)$property->situacion : Inmueble180::SITUACION_SIN_REFERENCIA,
            'refcatastral' => $property ? (string)$property->refcatastral : '',
            'tipovia' => $property ? (string)$property->tipovia : '',
            'nombrevia' => $property ? (string)$property->nombrevia : '',
            'tiponumeracion' => $property ? (string)$property->tiponumeracion : '',
            'numero' => $property ? (string)$property->numero : '',
            'calificador' => $property ? (string)$property->calificador : '',
            'bloque' => $property ? (string)$property->bloque : '',
            'portal' => $property ? (string)$property->portal : '',
            'escalera' => $property ? (string)$property->escalera : '',
            'planta' => $property ? (string)$property->planta : '',
            'puerta' => $property ? (string)$property->puerta : '',
            'complemento' => $property ? (string)$property->complemento : '',
            'localidad' => $property ? (string)$property->localidad : '',
            'municipio' => $property ? (string)$property->municipio : '',
            'codmunicipio' => $property ? (string)$property->codmunicipio : '',
            'provinciainmueble' => $property ? (string)$property->codprovincia : '',
            'codpostal' => $property ? (string)$property->codpostal : '',
            'numfacturas' => 0,
            'facturas' => [],
            'reparto' => false,
        ];
    }

    /**
     * Normaliza un nombre de provincia para compararlo: minúsculas, sin tildes y sin espacios sobrantes.
     *
     * @param string $name
     *
     * @return string
     */
    protected static function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = strtr($name, ['á' => 'a', 'à' => 'a', 'é' => 'e', 'è' => 'e', 'í' => 'i', 'ï' => 'i', 'ó' => 'o',
            'ò' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c']);
        return preg_replace('/\s+/', ' ', $name);
    }

    /**
     * Nombre del perceptor: la razón social del proveedor o, si no tiene, su nombre.
     *
     * @param Proveedor $supplier
     *
     * @return string
     */
    protected static function supplierName(Proveedor $supplier): string
    {
        return (string)(empty($supplier->razonsocial) ? $supplier->nombre : $supplier->razonsocial);
    }
}
