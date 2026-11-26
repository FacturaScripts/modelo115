<?php
/**
 * This file is part of Modelo115 plugin for FacturaScripts
 * Copyright (C) 2020-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
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
use FacturaScripts\Dinamic\Lib\Modelo115File;
use FacturaScripts\Dinamic\Lib\Modelo180;
use FacturaScripts\Dinamic\Lib\Modelo180File;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Retencion;

/**
 * Pantalla de los modelos 115 (trimestral) y 180 (resumen anual) de retenciones de arrendamientos.
 *
 * En los trimestres muestra las casillas del 115 y las facturas del periodo. En el periodo anual muestra
 * además el detalle del 180 por arrendador e inmueble y permite descargar su fichero de presentación.
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Modelo115 extends Controller
{
    /** @var string ejercicio seleccionado */
    public $codejercicio;

    /** @var string retención por la que filtrar las facturas (vacío = todas) */
    public $codretencion;

    /** @var string persona con quién relacionarse (registro de tipo 1 del 180) */
    public $contact = '';

    /** @var string tipo de declaración del 180: '' normal, 'C' complementaria o 'S' sustitutiva */
    public $declarationType = '';

    /** @var array opciones del fichero del modelo 115: tipo, iban y justificante */
    public $options115 = [];

    /** @var string periodo: T1-T4 o ANNUAL */
    public $period = 'T1';

    /** @var string teléfono de contacto (registro de tipo 1 del 180) */
    public $phone = '';

    /** @var string número identificativo de la declaración anterior del 180 (complementaria o sustitutiva) */
    public $previousReceipt = '';

    /** @var string número identificativo de la declaración del 180 */
    public $receipt = '';

    /** @var array resultados de la Lib Modelo115 */
    public $result = [];

    /** @var array registros y avisos del 180 (Lib Modelo180), solo en el periodo anual */
    public $result180 = [];

    /** @var float importe a deducir de autoliquidaciones anteriores */
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

    /**
     * Devuelve los periodos del selector (T1-T4 y ANNUAL, el resumen anual del modelo 180) con su clave de
     * traducción.
     *
     * @return array
     */
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

    /**
     * Devuelve las retenciones por las que se puede filtrar las facturas, ordenadas por descripción.
     *
     * @return Retencion[]
     */
    public function allRetentions(): array
    {
        return Retencion::all([], ['descripcion' => 'ASC']);
    }

    /**
     * Datos de la página: menú Informes, submenú Fiscalidad España.
     *
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['submenu'] = 'taxation-spain';
        $data['title'] = 'model-115-180';
        $data['icon'] = 'fa-solid fa-book';
        return $data;
    }

    /**
     * Indica si se está viendo el periodo anual (modelo 180) de un ejercicio existente.
     *
     * @return bool
     */
    public function isAnnual(): bool
    {
        return !empty($this->result) && $this->result['period'] === 'ANNUAL';
    }

    /**
     * Calcula el modelo con los filtros de la petición y ejecuta la acción solicitada.
     *
     * Los totales de las facturas (neto, IVA, recargo, IRPF y total) los calcula la Lib, que también usa el
     * endpoint de la API, para que ambos den las mismas cifras. En el periodo anual se calculan además los
     * registros del modelo 180.
     *
     * @param mixed $response
     * @param mixed $user
     * @param mixed $permissions
     */
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

        if ($this->isAnnual()) {
            $this->result180 = Modelo180::build($this->result['invoices']);
            $this->loadDeclarantData();
        }
        $this->loadFileOptions();

        $action = $this->request->inputOrQuery('action', '');
        if ($action === 'print') {
            $this->printAction();
        } elseif ($action === 'download') {
            $this->downloadAction();
        } elseif ($action === 'download-180') {
            $this->download180Action();
        }
    }

    /**
     * Descarga el fichero de presentación del modelo 180 si los datos del registro de tipo 1 son válidos.
     */
    protected function download180Action(): void
    {
        if (false === $this->isAnnual() || false === $this->validateDeclarantData()) {
            return;
        }

        // los datos obligatorios de los perceptores y los inmuebles que falten impiden generar el fichero
        $blocking = Modelo180::blockingWarnings($this->result180['warnings'] ?? []);
        if (!empty($blocking)) {
            foreach ($blocking as $warning) {
                Tools::log()->warning($warning['key'], $warning['params']);
            }
            Tools::log()->warning('model-180-fix-before-download');
            return;
        }

        $company = new Empresa();
        $company->load($this->result['exercise']->idempresa);
        $year = (int)date('Y', strtotime($this->result['exercise']->fechainicio));

        $content = Modelo180File::generate($company, $year, $this->result180['records'], [
            'justificante' => $this->receipt,
            'tipo' => $this->declarationType,
            'anterior' => $this->previousReceipt,
            'telefono' => $this->phone,
            'contacto' => $this->contact,
        ]);

        $this->setTemplate(false);
        $this->response
            ->header('Content-Type', 'text/plain; charset=ISO-8859-1')
            ->header('Content-Disposition', 'attachment; filename="modelo180_' . $year . '.txt"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->setContent($content);
    }

    /**
     * Descarga el fichero de presentación del modelo 115 (trimestres) con el diseño de registro de la AEAT.
     * Si faltan datos de la declaración o alguna casilla no es válida, no lo genera y muestra los errores.
     */
    protected function downloadAction(): void
    {
        if (empty($this->result) || $this->isAnnual()) {
            return;
        }

        $company = new Empresa();
        $company->load($this->result['exercise']->idempresa);
        $errors = Modelo115File::validate($this->result, $company, $this->options115);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                Tools::log()->warning($error);
            }
            return;
        }

        $content = Modelo115File::generate($this->result, $company, $this->options115);
        $year = date('Y', strtotime($this->result['exercise']->fechainicio));
        $filename = $company->cifnif . '_' . $year . '_' . Modelo115File::periodCode($this->result['period']) . '.115';

        $this->setTemplate(false);
        $this->response
            ->header('Content-Type', 'text/plain; charset=ISO-8859-1')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->setContent($content);
    }

    /**
     * Devuelve el trimestre (T1-T4) en el que cae la fecha actual, el periodo por defecto de la pantalla.
     *
     * @return string
     */
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

    /**
     * Carga los datos del registro de tipo 1 del 180: los del formulario o, por defecto, los de la empresa
     * y el número identificativo 180 + año + 000001.
     */
    protected function loadDeclarantData(): void
    {
        $company = new Empresa();
        $company->load($this->result['exercise']->idempresa);
        $year = (int)date('Y', strtotime($this->result['exercise']->fechainicio));

        $this->receipt = preg_replace('/\D/', '', (string)$this->request->input('receipt', Modelo180File::defaultReceipt($year)));
        $this->declarationType = strtoupper((string)$this->request->input('declarationtype', ''));
        if (false === in_array($this->declarationType, ['', 'C', 'S'], true)) {
            $this->declarationType = '';
        }
        $this->previousReceipt = preg_replace('/\D/', '', (string)$this->request->input('previousreceipt', ''));
        $this->phone = preg_replace('/\D/', '', (string)$this->request->input('phone', $company->telefono1));
        $this->contact = Tools::noHtml(trim((string)$this->request->input('contact', $company->administrador)));
    }

    /**
     * Carga las opciones del fichero del modelo 115 del formulario: tipo de declaración, IBAN y justificante
     * de la declaración anterior.
     */
    protected function loadFileOptions(): void
    {
        $this->options115 = [
            'tipo' => (string)$this->request->input('tipo115', ''),
            'iban' => (string)$this->request->input('iban115', ''),
            'justificante' => preg_replace('/\D/', '', (string)$this->request->input('justificante115', '')),
        ];
    }

    /**
     * Exporta a PDF el resumen del modelo, los perceptores del 180 (solo en el periodo anual) y las facturas
     * con sus totales.
     */
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

        // perceptores del 180
        if ($this->isAnnual() && !empty($this->result180['records'])) {
            $this->printRecipients($exportManager);
        }

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
                $supplier => Tools::trans('totals'),
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

    /**
     * Añade al PDF la tabla de perceptores del modelo 180 (por arrendador e inmueble).
     *
     * @param ExportManager $exportManager
     */
    protected function printRecipients(ExportManager $exportManager): void
    {
        $nif = Tools::trans('cifnif');
        $supplier = Tools::trans('supplier');
        $province = Tools::trans('province');
        $property = Tools::trans('model-180-property');
        $reference = Tools::trans('cadastral-reference');
        $base = Tools::trans('tax-base');
        $percentage = '%';
        $retention = Tools::trans('retention');

        $rows = [];
        foreach ($this->result180['records'] as $record) {
            $rows[] = [
                $nif => $record['cifnif'],
                $supplier => $record['nombre'],
                $province => $record['codprovincia'],
                $property => $record['inmueble'],
                $reference => $record['refcatastral'],
                $base => Tools::money($record['base']),
                $percentage => Tools::number($record['porcentaje']),
                $retention => Tools::money($record['retenciones']),
            ];
        }
        $rows[] = [
            $nif => '',
            $supplier => Tools::trans('totals'),
            $province => '',
            $property => '',
            $reference => '',
            $base => Tools::money($this->result180['taxbase']),
            $percentage => '',
            $retention => Tools::money($this->result180['retentions']),
        ];
        $exportManager->addTablePage([$nif, $supplier, $province, $property, $reference, $base, $percentage, $retention], $rows);
    }

    /**
     * Valida los datos del registro de tipo 1: número identificativo de 13 dígitos que empiece por 180 y,
     * en las complementarias y sustitutivas, el de la declaración anterior.
     *
     * @return bool
     */
    protected function validateDeclarantData(): bool
    {
        if (false === (bool)preg_match('/^180\d{10}$/', $this->receipt)) {
            Tools::log()->warning('model-180-invalid-receipt');
            return false;
        }

        if ($this->declarationType !== '' && false === (bool)preg_match('/^180\d{10}$/', $this->previousReceipt)) {
            Tools::log()->warning('model-180-invalid-previous-receipt');
            return false;
        }

        return true;
    }
}
