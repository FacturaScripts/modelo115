<?php
/**
 * This file is part of Modelo115 plugin for FacturaScripts
 * Copyright (C) 2020 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Retencion;

/**
 * Description of Modelo115
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Modelo115 extends Controller
{

    /**
     *
     * @var string
     */
    public $codejercicio;

    /**
     *
     * @var string
     */
    public $codretencion;

    /**
     *
     * @var string
     */
    public $dateEnd;

    /**
     *
     * @var string
     */
    public $dateStart;

    /**
     *
     * @var int
     */
    protected $idempresa;

    /**
     *
     * @var FacturaProveedor[]
     */
    public $invoices = [];

    /**
     *
     * @var int
     */
    public $numrecipients = 0;

    /**
     *
     * @var string
     */
    public $period = 'T1';

    /**
     *
     * @var float
     */
    public $result = 0.0;

    /**
     *
     * @var float
     */
    public $retentions = 0.0;

    /**
     *
     * @var float
     */
    public $taxbase = 0.0;

    /**
     *
     * @var float
     */
    public $todeduct = 0.0;

    /**
     * 
     * @return Ejercicio[]
     */
    public function allExercises()
    {
        $exercise = new Ejercicio();
        return $exercise->all([], ['nombre' => 'DESC'], 0, 0);
    }

    /**
     * 
     * @return array
     */
    public function allPeriods()
    {
        return [
            'T1' => 'first-trimester',
            'T2' => 'second-trimester',
            'T3' => 'third-trimester',
            'T4' => 'fourth-trimester'
        ];
    }

    /**
     * 
     * @return array
     */
    public function allRetentions()
    {
        $retention = new Retencion();
        return $retention->all([], ['descripcion' => 'ASC'], 0, 0);
    }

    /**
     * 
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'model-115';
        $data['icon'] = 'fas fa-book';
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->loadDates();
        $this->loadInvoices();
        $this->loadResults();
    }

    protected function loadDates()
    {
        $this->codejercicio = $this->request->request->get('codejercicio', '');
        $this->period = $this->request->request->get('period', $this->period);

        $exercise = new Ejercicio();
        $exercise->loadFromCode($this->codejercicio);

        $this->idempresa = $exercise->idempresa;

        switch ($this->period) {
            case 'T1':
                $this->dateStart = \date('01-01-Y', \strtotime($exercise->fechainicio));
                $this->dateEnd = \date('31-03-Y', \strtotime($exercise->fechainicio));
                break;

            case 'T2':
                $this->dateStart = \date('01-04-Y', \strtotime($exercise->fechainicio));
                $this->dateEnd = \date('30-06-Y', \strtotime($exercise->fechainicio));
                break;

            case 'T3':
                $this->dateStart = \date('01-07-Y', \strtotime($exercise->fechainicio));
                $this->dateEnd = \date('30-09-Y', \strtotime($exercise->fechainicio));
                break;

            default:
                $this->dateStart = \date('01-10-Y', \strtotime($exercise->fechainicio));
                $this->dateEnd = \date('31-12-Y', \strtotime($exercise->fechainicio));
                break;
        }
    }

    protected function loadInvoices()
    {
        $invoiceModel = new FacturaProveedor();
        $where = [
            new DataBaseWhere('fecha', $this->dateStart, '>='),
            new DataBaseWhere('fecha', $this->dateEnd, '<='),
            new DataBaseWhere('idempresa', $this->idempresa),
            new DataBaseWhere('totalirpf', 0.0, '!=')
        ];

        $this->codretencion = $this->request->request->get('codretencion', '');
        $retention = new Retencion();
        if (!empty($this->codretencion) && $retention->loadFromCode($this->codretencion)) {
            $where[] = new DataBaseWhere('irpf', $retention->porcentaje);
        }

        $order = ['fecha' => 'ASC', 'numero' => 'ASC'];
        $this->invoices = $invoiceModel->all($where, $order, 0, 0);
    }

    protected function loadResults()
    {
        $suppliers = [];
        foreach ($this->invoices as $invoice) {
            $suppliers[$invoice->codproveedor] = $invoice->codproveedor;
            $this->taxbase += $invoice->neto;
            $this->retentions += $invoice->totalirpf;
        }

        $this->numrecipients = \count($suppliers);
        $this->todeduct = (float) $this->request->request->get('todeduct');
        $this->result = $this->retentions - $this->todeduct;
    }
}
