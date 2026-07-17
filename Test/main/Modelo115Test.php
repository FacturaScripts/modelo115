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

use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Dinamic\Model\Retencion;
use FacturaScripts\Dinamic\Model\Subcuenta;
use FacturaScripts\Plugins\Modelo115\Lib\Modelo115;

final class Modelo115Test extends Modelo115TestCase
{
    public function testEjercicioInexistente(): void
    {
        $this->assertSame([], Modelo115::generate('NO-EXISTE', 'T1', ''));
    }

    public function testGenerate(): void
    {
        $exercise = $this->getCurrentExercise();
        $before = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '');
        $this->assertNotEmpty($before);

        // creamos una factura de alquiler: 1000€ de base con el 19% de retención
        $invoice = $this->createSupplierInvoice(1000.0, 19.0);

        $result = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '');
        $this->assertNotEmpty($result);

        // casilla 01: número de perceptores
        $this->assertSame($before['numrecipients'] + 1, $result['numrecipients']);

        // casilla 02: base de las retenciones
        $this->assertEqualsWithDelta($before['taxbase'] + 1000.0, $result['taxbase'], 0.001);

        // casilla 03: retenciones practicadas
        $this->assertEqualsWithDelta($before['retentions'] + 190.0, $result['retentions'], 0.001);

        // casilla 05: resultado a ingresar
        $this->assertEqualsWithDelta($before['result'] + 190.0, $result['result'], 0.001);

        // la factura debe estar en el listado
        $this->assertCount(count($before['invoices']) + 1, $result['invoices']);
        $codes = array_map(static function (FacturaProveedor $item) {
            return $item->codigo;
        }, $result['invoices']);
        $this->assertContains($invoice->codigo, $codes);
    }

    public function testGenerateConDeduccion(): void
    {
        $exercise = $this->getCurrentExercise();
        $this->createSupplierInvoice(1000.0, 19.0);

        // casilla 04: a deducir de periodos anteriores
        $result = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '', 50.0);
        $this->assertNotEmpty($result);

        $this->assertEqualsWithDelta(50.0, $result['todeduct'], 0.001);
        $this->assertEqualsWithDelta($result['retentions'] - 50.0, $result['result'], 0.001);
    }

    public function testExcluyeFacturasSinRetencion(): void
    {
        $exercise = $this->getCurrentExercise();
        $before = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '');

        // una factura sin retención no debe aparecer en el modelo
        $invoice = $this->createSupplierInvoice(500.0, 0.0);

        $result = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '');
        $this->assertNotEmpty($result);

        $this->assertSame($before['numrecipients'], $result['numrecipients']);
        $this->assertEqualsWithDelta($before['taxbase'], $result['taxbase'], 0.001);
        $this->assertEqualsWithDelta($before['retentions'], $result['retentions'], 0.001);

        $codes = array_map(static function (FacturaProveedor $item) {
            return $item->codigo;
        }, $result['invoices']);
        $this->assertNotContains($invoice->codigo, $codes);
    }

    public function testFiltroPorRetencion(): void
    {
        $exercise = $this->getCurrentExercise();

        // creamos la retención del 7%
        $retencion = new Retencion();
        $retencion->codretencion = 'TEST7';
        $retencion->descripcion = 'IRPF test 7%';
        $retencion->porcentaje = 7;
        $this->assertTrue($retencion->save());
        $this->addCleanup(static function () use ($retencion) {
            $retencion->delete();
        });

        $before = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), $retencion->codretencion);

        // una factura con el 7% y otra con el 19% de retención
        $invoice7 = $this->createSupplierInvoice(1000.0, 7.0);
        $invoice19 = $this->createSupplierInvoice(1000.0, 19.0);

        // al filtrar por la retención del 7%, solo debe salir la primera factura
        $result = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), $retencion->codretencion);
        $this->assertNotEmpty($result);

        $this->assertEqualsWithDelta($before['taxbase'] + 1000.0, $result['taxbase'], 0.001);
        $this->assertEqualsWithDelta($before['retentions'] + 70.0, $result['retentions'], 0.001);

        $codes = array_map(static function (FacturaProveedor $item) {
            return $item->codigo;
        }, $result['invoices']);
        $this->assertContains($invoice7->codigo, $codes);
        $this->assertNotContains($invoice19->codigo, $codes);
    }

    /**
     * Las retenciones que no van a una subcuenta de alquileres (cuenta especial
     * IRPFA) corresponden al modelo 111 y no deben aparecer en el modelo 115.
     * Issue 110-286-046.
     */
    public function testExcluyeRetencionesProfesionales(): void
    {
        $exercise = $this->getCurrentExercise();

        // creamos la subcuenta de retenciones de alquileres con la cuenta especial IRPFA
        $subAlquiler = new Subcuenta();
        $subAlquiler->codcuenta = '4751';
        $subAlquiler->codcuentaesp = 'IRPFA';
        $subAlquiler->codejercicio = $exercise->codejercicio;
        $subAlquiler->codsubcuenta = '4751000019';
        $subAlquiler->descripcion = 'HP acreedora retenciones alquileres';
        $this->assertTrue($subAlquiler->save());

        // creamos la retención de alquileres del 18% sobre esa subcuenta
        // (usamos un porcentaje que no existe por defecto para que la
        // contabilización use esta retención y no otra)
        $retencion = new Retencion();
        $retencion->codretencion = 'ALQ18';
        $retencion->codsubcuentaacr = $subAlquiler->codsubcuenta;
        $retencion->descripcion = 'IRPF alquileres 18%';
        $retencion->porcentaje = 18;
        $this->assertTrue($retencion->save());
        $this->addCleanup(static function () use ($retencion, $subAlquiler) {
            $retencion->delete();
            $subAlquiler->delete();
        });

        $before = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '');

        // una factura de alquiler al 18% y otra profesional al 15% (retención
        // por defecto IRPF15, que contabiliza a la subcuenta genérica IRPFPR)
        $invoiceAlquiler = $this->createSupplierInvoice(1000.0, 18.0);
        $invoiceProfesional = $this->createSupplierInvoice(1000.0, 15.0);

        // ambas facturas deben tener asiento para poder clasificarlas
        $this->assertNotEmpty($invoiceAlquiler->idasiento);
        $this->assertNotEmpty($invoiceProfesional->idasiento);

        $result = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '');
        $this->assertNotEmpty($result);

        // solo debe salir la factura de alquiler
        $this->assertEqualsWithDelta($before['taxbase'] + 1000.0, $result['taxbase'], 0.001);
        $this->assertEqualsWithDelta($before['retentions'] + 180.0, $result['retentions'], 0.001);
        $this->assertSame($before['numrecipients'] + 1, $result['numrecipients']);

        $codes = array_map(static function (FacturaProveedor $item) {
            return $item->codigo;
        }, $result['invoices']);
        $this->assertContains($invoiceAlquiler->codigo, $codes);
        $this->assertNotContains($invoiceProfesional->codigo, $codes);
    }

    public function testNumPerceptores(): void
    {
        $exercise = $this->getCurrentExercise();
        $before = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '');

        // dos facturas del mismo proveedor cuentan como un solo perceptor
        $invoice1 = $this->createSupplierInvoice(1000.0, 19.0);
        $this->createSupplierInvoice(500.0, 19.0, $invoice1->getSubject());

        $result = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '');
        $this->assertNotEmpty($result);

        $this->assertSame($before['numrecipients'] + 1, $result['numrecipients']);
        $this->assertCount(count($before['invoices']) + 2, $result['invoices']);
        $this->assertEqualsWithDelta($before['retentions'] + 285.0, $result['retentions'], 0.001);
    }

    public function testPeriodos(): void
    {
        $exercise = $this->getCurrentExercise();
        $year = date('Y', strtotime($exercise->fechainicio));

        $expected = [
            'T1' => ['01-01-' . $year, '31-03-' . $year],
            'T2' => ['01-04-' . $year, '30-06-' . $year],
            'T3' => ['01-07-' . $year, '30-09-' . $year],
            'T4' => ['01-10-' . $year, '31-12-' . $year],
            'ANNUAL' => ['01-01-' . $year, '31-12-' . $year],
        ];
        foreach ($expected as $period => $dates) {
            $result = Modelo115::generate($exercise->codejercicio, $period, '');
            $this->assertSame($period, $result['period']);
            $this->assertSame($dates[0], $result['dateStart']);
            $this->assertSame($dates[1], $result['dateEnd']);
        }

        // un periodo desconocido se trata como el primer trimestre
        $result = Modelo115::generate($exercise->codejercicio, 'XX', '');
        $this->assertSame('T1', $result['period']);
        $this->assertSame('01-01-' . $year, $result['dateStart']);
        $this->assertSame('31-03-' . $year, $result['dateEnd']);
    }

    private function createSupplierInvoice(float $net, float $irpf, ?Proveedor $supplier = null): FacturaProveedor
    {
        if (null === $supplier) {
            $supplier = new Proveedor();
            $supplier->cifnif = mt_rand(1, 99999999) . 'J';
            $supplier->nombre = 'Arrendador ' . mt_rand(1, 999);
            $this->assertTrue($supplier->save());
            $this->addCleanup(static function () use ($supplier) {
                $supplier->delete();
            });
        }

        $invoice = new FacturaProveedor();
        $invoice->setSubject($supplier);
        $invoice->setDate(Tools::date(), $invoice->hora);
        $this->assertTrue($invoice->save());
        $this->addCleanup(static function () use ($invoice) {
            if ($invoice->exists()) {
                $invoice->delete();
            }
        });

        $line = $invoice->getNewLine();
        $line->cantidad = 1;
        $line->descripcion = 'Alquiler local';
        $line->pvpunitario = $net;
        $line->irpf = $irpf;
        $this->assertTrue($line->save());

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true));
        $this->assertEqualsWithDelta($net, $invoice->neto, 0.001);
        $this->assertEqualsWithDelta($net * $irpf / 100, $invoice->totalirpf, 0.001);

        return $invoice;
    }

    private function getCurrentExercise(): Ejercicio
    {
        $exercise = new Ejercicio();
        $exercise->idempresa = Tools::settings('default', 'idempresa');
        $this->assertTrue($exercise->loadFromDate(Tools::date()));
        return $exercise;
    }

    private function getCurrentPeriod(): string
    {
        return 'T' . ceil((int)date('n') / 3);
    }
}
