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

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\CuentaEspecial;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Dinamic\Model\Retencion;
use FacturaScripts\Dinamic\Model\Subcuenta;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo115
{
    /** @var string */
    protected static $codretencion = '';

    /** @var DataBase */
    protected static $dataBase;

    /** @var string */
    protected static $dateEnd = '';

    /** @var string */
    protected static $dateStart = '';

    /** @var Ejercicio */
    protected static $exercise;

    /** @var array */
    protected static $invoices = [];

    /** @var string */
    protected static $period = '';

    /** @var float */
    protected static $result = 0.0;

    /** @var float */
    protected static $retentions = 0.0;

    /** @var float */
    protected static $taxbase = 0.0;

    /** @var float */
    protected static $todeduct = 0.0;

    /** @var int */
    protected static $numrecipients = 0;

    public static function generate(string $codejercicio, string $period, string $codRetencion, float $todeduct = 0.0): array
    {
        // comprobamos que el ejercicio existe
        static::$exercise = new Ejercicio();
        if (false === static::$exercise->load($codejercicio)) {
            return [];
        }

        // inicializamos las variables
        static::$codretencion = $codRetencion;
        static::$dataBase = new DataBase();
        static::$period = strtoupper($period);
        static::$todeduct = $todeduct;

        // cargamos las fechas del periodo
        static::loadDates();

        // cargamos las facturas del periodo
        static::loadInvoices();

        // calculamos los resultados
        static::loadResults();

        return [
            'exercise' => static::$exercise,
            'period' => static::$period,
            'dateStart' => static::$dateStart,
            'dateEnd' => static::$dateEnd,
            'invoices' => static::$invoices,
            'taxbase' => static::$taxbase,
            'retentions' => static::$retentions,
            'todeduct' => static::$todeduct,
            'result' => static::$result,
            'numrecipients' => static::$numrecipients
        ];
    }

    protected static function loadDates(): void
    {
        // si el periodo no es T1, T2, T3, T4 o Annual, se asume que es el primer trimestre
        if (!in_array(static::$period, ['T1', 'T2', 'T3', 'T4', 'ANNUAL'])) {
            static::$period = 'T1';
        }

        switch (static::$period) {
            case 'T1':
                static::$dateStart = date('01-01-Y', strtotime(static::$exercise->fechainicio));
                static::$dateEnd = date('31-03-Y', strtotime(static::$exercise->fechainicio));
                break;

            case 'T2':
                static::$dateStart = date('01-04-Y', strtotime(static::$exercise->fechainicio));
                static::$dateEnd = date('30-06-Y', strtotime(static::$exercise->fechainicio));
                break;

            case 'T3':
                static::$dateStart = date('01-07-Y', strtotime(static::$exercise->fechainicio));
                static::$dateEnd = date('30-09-Y', strtotime(static::$exercise->fechainicio));
                break;

            case 'ANNUAL':
                static::$dateStart = date('01-01-Y', strtotime(static::$exercise->fechainicio));
                static::$dateEnd = date('31-12-Y', strtotime(static::$exercise->fechainicio));
                break;

            default:
                static::$dateStart = date('01-10-Y', strtotime(static::$exercise->fechainicio));
                static::$dateEnd = date('31-12-Y', strtotime(static::$exercise->fechainicio));
                break;
        }
    }

    protected static function loadInvoices(): void
    {
        $where = [
            Where::gte('fecha', static::$dateStart),
            Where::lte('fecha', static::$dateEnd),
            Where::eq('idempresa', static::$exercise->idempresa),
            Where::notEq('totalirpf', 0.0)
        ];

        $retention = new Retencion();
        if (!empty(static::$codretencion) && $retention->load(static::$codretencion)) {
            $where[] = Where::eq('irpf', $retention->porcentaje);
        }

        $order = ['fecha' => 'ASC', 'numero' => 'ASC'];
        $invoices = FacturaProveedor::all($where, $order, 0, 0);

        // excluimos las facturas cuyas retenciones no son de alquileres (IRPFA),
        // que van al modelo 111. Issue 110-286-046.
        static::$invoices = static::filterRentalInvoices($invoices);
    }

    /**
     * Devuelve solamente las facturas cuyo asiento tiene alguna partida en una
     * subcuenta de retenciones de alquileres (IRPFA). Si el ejercicio no tiene
     * subcuentas IRPFA, no podemos distinguir y se devuelven todas.
     *
     * @param FacturaProveedor[] $invoices
     * @return FacturaProveedor[]
     */
    protected static function filterRentalInvoices(array $invoices): array
    {
        $ids = static::getSubcuentasIRPFA();
        if (empty($ids)) {
            return $invoices;
        }

        // obtenemos los asientos de las facturas
        $asientos = [];
        foreach ($invoices as $invoice) {
            if ($invoice->idasiento) {
                $asientos[$invoice->idasiento] = $invoice->idasiento;
            }
        }
        if (empty($asientos)) {
            return $invoices;
        }

        // asientos con alguna partida en una subcuenta IRPFA
        $rentalAsientos = [];
        $sql = 'SELECT DISTINCT idasiento FROM ' . Partida::tableName()
            . ' WHERE idasiento IN (' . implode(',', $asientos) . ')'
            . ' AND idsubcuenta IN (' . implode(',', $ids) . ')';
        foreach (static::$dataBase->select($sql) as $row) {
            $rentalAsientos[$row['idasiento']] = $row['idasiento'];
        }

        $result = [];
        foreach ($invoices as $invoice) {
            // si la factura no tiene asiento, no podemos clasificarla y la mantenemos
            if (empty($invoice->idasiento) || isset($rentalAsientos[$invoice->idasiento])) {
                $result[] = $invoice;
            }
        }
        return $result;
    }

    /**
     * Devuelve los IDs de las subcuentas de retenciones de alquileres (IRPFA) del ejercicio.
     *
     * @return array
     */
    protected static function getSubcuentasIRPFA(): array
    {
        $ids = [];

        // subcuentas de la cuenta con cuenta especial IRPFA
        $special = new CuentaEspecial();
        if ($special->loadWhere([Where::eq('codcuentaesp', 'IRPFA')])) {
            foreach ($special->getCuenta(static::$exercise->codejercicio)->getSubcuentas() as $subcuenta) {
                $ids[$subcuenta->id()] = $subcuenta->id();
            }
        }

        // subcuentas marcadas directamente con la cuenta especial IRPFA
        $where = [
            Where::eq('codejercicio', static::$exercise->codejercicio),
            Where::eq('codcuentaesp', 'IRPFA'),
        ];
        foreach (Subcuenta::all($where) as $subcuenta) {
            $ids[$subcuenta->id()] = $subcuenta->id();
        }

        return $ids;
    }

    protected static function loadResults(): void
    {
        $recipients = [];
        foreach (static::$invoices as $invoice) {
            $recipients[$invoice->codproveedor] = $invoice->codproveedor;
            static::$taxbase += $invoice->neto;
            static::$retentions += $invoice->totalirpf;
        }

        static::$numrecipients = count($recipients);
        static::$result = static::$retentions - static::$todeduct;
    }
}