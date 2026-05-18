<?php
/**
 * This file is part of Modelo115 plugin for FacturaScripts
 * Copyright (C) 2020-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
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

namespace FacturaScripts\Plugins\Modelo115\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\DataSrc\Ejercicios;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\Modelo115 as LibModelo115;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Retencion;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo115 extends Controller
{
    /** @var string */
    public $codejercicio;

    /** @var string */
    public $codretencion;

    /** @var array */
    public $result = [];

    /** @var string */
    public $period = 'T1';

    /** @var float */
    public $todeduct = 0.0;

    /**
     * @param int|null $idempresa
     * @return Ejercicio[]
     */
    public function allExercises(?int $idempresa): array
    {
        if (empty($idempresa)) {
            return Ejercicios::all();
        }

        $list = [];
        foreach (Ejercicios::all() as $exercise) {
            if ($exercise->idempresa === $idempresa) {
                $list[] = $exercise;
            }
        }
        return $list;
    }

    public function allPeriods(): array
    {
        return [
            'T1' => 'first-trimester',
            'T2' => 'second-trimester',
            'T3' => 'third-trimester',
            'T4' => 'fourth-trimester',
            'ANNUAL' => 'annual-180',
        ];
    }

    public function allRetentions(): array
    {
        return Retencion::all([], ['descripcion' => 'ASC']);
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'model-115-180';
        $data['icon'] = 'fa-solid fa-book';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->codejercicio = $this->request->inputOrQuery('codejercicio', date('Y'));
        $this->period = $this->request->inputOrQuery('period', $this->getCurrentPeriod());
        $this->codretencion = $this->request->inputOrQuery('codretencion', '');
        $this->todeduct = (float)$this->request->inputOrQuery('todeduct', 0);

        $this->result = LibModelo115::generate(
            $this->codejercicio,
            $this->period,
            $this->codretencion,
            $this->todeduct
        );

        $totalNeto = 0.0;
        $totalIva = 0.0;
        $totalRecargo = 0.0;
        $totalIrpf = 0.0;
        $totalTotal = 0.0;
        foreach ($this->result['invoices'] ?? [] as $invoice) {
            $totalNeto += $invoice->neto;
            $totalIva += $invoice->totaliva;
            $totalRecargo += $invoice->totalrecargo;
            $totalIrpf += $invoice->totalirpf;
            $totalTotal += $invoice->total;
        }
        $this->result['totalNeto'] = $totalNeto;
        $this->result['totalIva'] = $totalIva;
        $this->result['totalRecargo'] = $totalRecargo;
        $this->result['totalIrpf'] = $totalIrpf;
        $this->result['totalTotal'] = $totalTotal;

        $action = $this->request->inputOrQuery('action', '');
        if ($action === 'print') {
            $this->printAction();
        }
    }

    protected function printAction(): void
    {
        if (empty($this->result)) {
            return;
        }

        $this->setTemplate(false);

        $exportManager = new ExportManager();
        $exportManager->newDoc('PDF', Tools::trans('model-115-180'));

        // resumen
        $recipients = Tools::trans('number-recipients');
        $taxbase = Tools::trans('tax-base');
        $retentions = Tools::trans('retentions');
        $todeduct = Tools::trans('to-deduct');
        $result = Tools::trans('result');

        $exportManager->addTablePage(
            [$recipients, $taxbase, $retentions, $todeduct, $result],
            [[
                $recipients => $this->result['numrecipients'],
                $taxbase => Tools::money($this->result['taxbase']),
                $retentions => Tools::money($this->result['retentions']),
                $todeduct => Tools::money($this->result['todeduct']),
                $result => Tools::money($this->result['result']),
            ]]
        );

        // facturas
        if (!empty($this->result['invoices'])) {
            $invoice = Tools::trans('invoice');
            $supplier = Tools::trans('supplier');
            $net = Tools::trans('net');
            $taxes = Tools::trans('taxes');
            $surcharge = Tools::trans('surcharge');
            $retention = Tools::trans('retention');
            $total = Tools::trans('total');
            $date = Tools::trans('date');

            $rows = [];
            foreach ($this->result['invoices'] as $item) {
                $rows[] = [
                    $invoice => $item->codigo,
                    $supplier => $item->nombre,
                    $net => Tools::money($item->neto),
                    $taxes => Tools::money($item->totaliva),
                    $surcharge => Tools::money($item->totalrecargo),
                    $retention => Tools::money($item->totalirpf),
                    $total => Tools::money($item->total),
                    $date => $item->fecha,
                ];
            }
            $rows[] = [
                $invoice => '',
                $supplier => Tools::trans('total'),
                $net => Tools::money($this->result['totalNeto']),
                $taxes => Tools::money($this->result['totalIva']),
                $surcharge => Tools::money($this->result['totalRecargo']),
                $retention => Tools::money($this->result['totalIrpf']),
                $total => Tools::money($this->result['totalTotal']),
                $date => '',
            ];
            $exportManager->addTablePage(
                [$invoice, $supplier, $net, $taxes, $surcharge, $retention, $total, $date],
                $rows
            );
        }

        $exportManager->show($this->response);
    }

    protected function getCurrentPeriod(): string
    {
        // obtenemos el número del trimestre en el que se encuentra la fecha actual
        $month = date('n');
        return match ($month) {
            1, 2, 3 => 'T1',
            4, 5, 6 => 'T2',
            7, 8, 9 => 'T3',
            10, 11, 12 => 'T4',
            default => 'T1',
        };
    }
}
