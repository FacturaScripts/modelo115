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

namespace FacturaScripts\Test\Plugins;

use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Retencion;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Plugins\Modelo115\Lib\Modelo115;
use FacturaScripts\Plugins\Modelo115\Lib\Modelo115Api;

/**
 * Pruebas de la respuesta del endpoint GET /api/3/modelo115 (Modelo115Api): datos, periodos, errores,
 * resolución del ejercicio por empresa y año, detalle y avisos. El modelo 180 se prueba en Modelo180ApiTest.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo115ApiTest extends Modelo115TestCase
{
    /**
     * Sin subcuentas IRPFA avisa de que se incluyen todas las facturas con IRPF; con ellas, no.
     */
    public function testAvisoSubcuentasAlquiler(): void
    {
        $exercise = $this->getCurrentExercise();
        $params = ['codejercicio' => $exercise->codejercicio, 'periodo' => $this->getCurrentPeriod()];

        $data = $this->callApi($params);
        $this->assertTrue($this->hasWarning($data, 'IRPFA'));

        // creamos una subcuenta de retenciones de alquileres
        $subAlquiler = new Subcuenta();
        $subAlquiler->codcuenta = '4751';
        $subAlquiler->codcuentaesp = 'IRPFA';
        $subAlquiler->codejercicio = $exercise->codejercicio;
        $subAlquiler->codsubcuenta = '4751000019';
        $subAlquiler->descripcion = 'HP acreedora retenciones alquileres';
        $this->assertTrue($subAlquiler->save());
        $this->addCleanup(static function () use ($subAlquiler) {
            $subAlquiler->delete();
        });

        $data = $this->callApi($params);
        $this->assertFalse($this->hasWarning($data, 'IRPFA'));
    }

    /**
     * Con detalle=1 incluye las facturas y los perceptores como filas planas.
     */
    public function testDetalle(): void
    {
        $exercise = $this->getCurrentExercise();
        $invoice = $this->createSupplierInvoice(1000.0, 19.0);

        $data = $this->callApi([
            'codejercicio' => $exercise->codejercicio,
            'periodo' => $this->getCurrentPeriod(),
            'detalle' => 'true',
        ]);
        $this->assertArrayHasKey('facturas', $data['detalle']);
        $this->assertArrayHasKey('perceptores', $data['detalle']);

        $row = null;
        foreach ($data['detalle']['facturas'] as $item) {
            if ($item['idfactura'] === (int)$invoice->idfactura) {
                $row = $item;
            }
        }
        $this->assertNotNull($row);
        $this->assertSame(
            ['idfactura', 'codigo', 'fecha', 'codproveedor', 'nombre', 'cifnif', 'neto', 'irpf', 'totalirpf', 'total'],
            array_keys($row)
        );
        $this->assertSame($invoice->codigo, $row['codigo']);
        $this->assertSame(date('Y-m-d', strtotime($invoice->fecha)), $row['fecha']);
        $this->assertSame(1000.0, $row['neto']);
        $this->assertSame(19.0, $row['irpf']);
        $this->assertSame(190.0, $row['totalirpf']);

        $this->assertSame(
            ['codproveedor', 'nombre', 'cifnif', 'base', 'retenciones', 'num_facturas'],
            array_keys($data['detalle']['perceptores'][0])
        );
    }

    /**
     * Errores de los parámetros: periodo ausente o no admitido, ejercicio, empresa, año, retención e importe.
     */
    public function testErrores(): void
    {
        $exercise = $this->getCurrentExercise();

        $code = 0;
        $data = $this->callApi(['codejercicio' => $exercise->codejercicio], $code);
        $this->assertSame(400, $code);
        $this->assertSame('missing-period', $data['message']);

        foreach (['T5', '03', 'XX', 'S1', 'ANUAL'] as $period) {
            $data = $this->callApi(['codejercicio' => $exercise->codejercicio, 'periodo' => $period], $code);
            $this->assertSame(400, $code, $period);
            $this->assertSame('error', $data['status']);
            $this->assertSame('invalid-period', $data['message']);
            $this->assertSame(Modelo115Api::PERIODS, $data['allowed']);
        }

        $data = $this->callApi(['codejercicio' => 'NO-EXISTE', 'periodo' => 'T1'], $code);
        $this->assertSame(404, $code);
        $this->assertSame('exercise-not-found', $data['message']);

        $data = $this->callApi(['idempresa' => '999999', 'periodo' => 'T1'], $code);
        $this->assertSame(404, $code);
        $this->assertSame('company-not-found', $data['message']);

        $data = $this->callApi(['anio' => '1900', 'periodo' => 'T1'], $code);
        $this->assertSame(404, $code);
        $this->assertSame('exercise-not-found', $data['message']);

        $data = $this->callApi(['anio' => '20X6', 'periodo' => 'T1'], $code);
        $this->assertSame(400, $code);
        $this->assertSame('invalid-year', $data['message']);

        $data = $this->callApi(['codejercicio' => $exercise->codejercicio, 'periodo' => 'T1', 'codretencion' => 'NO-EXISTE'], $code);
        $this->assertSame(404, $code);
        $this->assertSame('retention-not-found', $data['message']);

        $data = $this->callApi(['codejercicio' => $exercise->codejercicio, 'periodo' => 'T1', 'a_deducir' => 'abc'], $code);
        $this->assertSame(400, $code);
        $this->assertSame('invalid-parameter', $data['message']);
    }

    /**
     * El filtro por retención solo incluye las facturas con su porcentaje.
     */
    public function testFiltroRetencion(): void
    {
        $exercise = $this->getCurrentExercise();

        $retencion = new Retencion();
        $retencion->codretencion = 'TAPI7';
        $retencion->descripcion = 'IRPF test api 7%';
        $retencion->porcentaje = 7;
        $this->assertTrue($retencion->save());
        $this->addCleanup(static function () use ($retencion) {
            $retencion->delete();
        });

        $this->createSupplierInvoice(1000.0, 7.0);
        $this->createSupplierInvoice(1000.0, 19.0);

        $data = $this->callApi([
            'codejercicio' => $exercise->codejercicio,
            'periodo' => $this->getCurrentPeriod(),
            'codretencion' => $retencion->codretencion,
            'detalle' => '1',
        ]);
        $this->assertSame('TAPI7', $data['parametros']['codretencion']);
        $this->assertNotEmpty($data['detalle']['facturas']);
        foreach ($data['detalle']['facturas'] as $row) {
            $this->assertSame(7.0, $row['irpf']);
        }
    }

    /**
     * Normaliza los periodos: T1-T4, 1T-4T, meses y las variantes del anual.
     */
    public function testNormalizarPeriodo(): void
    {
        $this->assertSame('T1', Modelo115Api::normalizePeriod(' t1 '));
        $this->assertSame('T4', Modelo115Api::normalizePeriod('4T'));
        $this->assertSame('03', Modelo115Api::normalizePeriod('3'));
        $this->assertSame('12', Modelo115Api::normalizePeriod('12'));
        foreach (['ANUAL', 'annual', 'Y', 'a', '0A'] as $value) {
            $this->assertSame('ANUAL', Modelo115Api::normalizePeriod($value), $value);
        }
        $this->assertSame('T5', Modelo115Api::normalizePeriod('t5'));
    }

    /**
     * Sin codejercicio busca el ejercicio de la empresa (la predeterminada si no se indica) cuyo inicio cae
     * en el año indicado (el actual si no se indica).
     */
    public function testResolucionEmpresaAnio(): void
    {
        $exercise = $this->getCurrentExercise();
        $year = date('Y', strtotime($exercise->fechainicio));

        $code = 0;
        $data = $this->callApi(['idempresa' => (string)$exercise->idempresa, 'anio' => $year, 'periodo' => '1T'], $code);
        $this->assertSame(200, $code);
        $this->assertSame($exercise->codejercicio, $data['ejercicio']['codejercicio']);
        $this->assertSame((int)$exercise->idempresa, $data['empresa']['idempresa']);
        $this->assertSame('T1', $data['periodo']);

        // sin empresa ni año: la predeterminada y el año actual
        $data = $this->callApi(['periodo' => 'T1'], $code);
        $this->assertSame(200, $code);
        $this->assertSame((int)Tools::settings('default', 'idempresa'), $data['empresa']['idempresa']);
        $this->assertSame(date('Y'), substr($data['ejercicio']['fechainicio'], 0, 4));
    }

    /**
     * La respuesta trae los datos del contrato y las mismas cifras que la Lib que usa la pantalla.
     */
    public function testRespuesta(): void
    {
        $exercise = $this->getCurrentExercise();
        $this->createSupplierInvoice(1000.0, 19.0);
        $period = $this->getCurrentPeriod();

        $code = 0;
        $data = $this->callApi(['codejercicio' => $exercise->codejercicio, 'periodo' => $period, 'a_deducir' => '10,5'], $code);
        $this->assertSame(200, $code);

        $this->assertSame('115', $data['modelo']);
        $this->assertArrayNotHasKey('modelo_anual', $data);
        $ini = parse_ini_file(Tools::folder('Plugins', 'Modelo115', 'facturascripts.ini'));
        $this->assertSame((float)$ini['version'], $data['plugin_version']);
        $this->assertSame($exercise->codejercicio, $data['ejercicio']['codejercicio']);
        $this->assertSame($period, $data['periodo']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['fechainicio']);
        $this->assertSame(['codretencion' => '', 'a_deducir' => 10.5], $data['parametros']);
        $this->assertArrayNotHasKey('detalle', $data);
        $this->assertIsArray($data['avisos']);

        // mismas cifras que la Lib
        $result = Modelo115::generate($exercise->codejercicio, $period, '', 10.5);
        $this->assertSame((int)$result['numrecipients'], $data['resumen']['perceptores']);
        $this->assertSame(round($result['taxbase'], 2), $data['resumen']['base']);
        $this->assertSame(round($result['retentions'], 2), $data['resumen']['retenciones']);
        $this->assertSame(10.5, $data['resumen']['a_deducir']);
        $this->assertSame(round($result['result'], 2), $data['resumen']['resultado']);
        $this->assertSame(count($result['invoices']), $data['resumen']['num_facturas']);
        $this->assertSame(round($result['totalTotal'], 2), $data['resumen']['total']);
        $this->assertSame(round($result['retentions'] - 10.5, 2), $data['resultado']);

        // casillas oficiales 01 a 05
        $this->assertSame(['01', '02', '03', '04', '05'], array_map('strval', array_keys($data['casillas'])));
        $this->assertSame($data['resumen']['perceptores'], $data['casillas']['01']);
        $this->assertSame($data['resumen']['retenciones'], $data['casillas']['03']);
        $this->assertSame($data['resultado'], $data['casillas']['05']);

        // y la llamada repetida no acumula importes
        $again = $this->callApi(['codejercicio' => $exercise->codejercicio, 'periodo' => $period, 'a_deducir' => '10,5']);
        $this->assertSame($data['resumen'], $again['resumen']);
    }

    /**
     * Llama a Modelo115Api::build() y devuelve el cuerpo de la respuesta.
     *
     * @param array $params parámetros de la petición
     * @param int|null $code código HTTP de la respuesta
     *
     * @return array
     */
    private function callApi(array $params, ?int &$code = null): array
    {
        $result = Modelo115Api::build($params);
        $code = $result['code'];
        return $result['body'];
    }

    /**
     * Indica si la respuesta tiene algún aviso que contenga el texto indicado.
     *
     * @param array $data respuesta del endpoint
     * @param string $text texto a buscar
     *
     * @return bool
     */
    private function hasWarning(array $data, string $text): bool
    {
        foreach ($data['avisos'] as $warning) {
            if (str_contains($warning, $text)) {
                return true;
            }
        }
        return false;
    }
}
