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
use FacturaScripts\Core\Model\User;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Inmueble180;
use FacturaScripts\Dinamic\Model\PagoProveedor;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

class Modelo115TestCase extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    /**
     * Cleanup callbacks to run after each test in reverse order.
     *
     * @var array<int, callable>
     */
    private array $cleanupCallbacks = [];

    public function setUp(): void
    {
        // inicializamos los modelos para que se creen las
        // tablas necesarias y no de error de Foreign Key
        new User();
        new Inmueble180();

        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    protected function addCleanup(callable $callback): void
    {
        $this->cleanupCallbacks[] = $callback;
    }

    protected function tearDown(): void
    {
        while ($callback = array_pop($this->cleanupCallbacks)) {
            try {
                $callback();
            } catch (\Throwable $th) {
                error_log($th->getMessage());
            }
        }

        $this->logErrors();
    }

    /**
     * Crea un inmueble del modelo 180 del proveedor, en Madrid y con referencia catastral, con los datos
     * indicados encima, y programa su borrado al terminar el test.
     *
     * @param Proveedor $supplier arrendador
     * @param array $data campos que se quieren cambiar
     *
     * @return Inmueble180
     */
    protected function createProperty(Proveedor $supplier, array $data = []): Inmueble180
    {
        $property = new Inmueble180();
        $property->codproveedor = $supplier->codproveedor;
        $property->descripcion = 'Local ' . mt_rand(1, 999);
        $property->situacion = Inmueble180::SITUACION_COMUN;
        $property->refcatastral = '9872023VH5797S0001WX';
        $property->tipovia = 'CL';
        $property->nombrevia = 'Mayor';
        $property->tiponumeracion = 'NUM';
        $property->numero = '12';
        $property->municipio = 'Madrid';
        $property->codmunicipio = '28079';
        $property->codpostal = '28001';
        foreach ($data as $key => $value) {
            $property->{$key} = $value;
        }
        $this->assertTrue($property->save());
        $this->addCleanup(static function () use ($property) {
            $property->delete();
        });

        return $property;
    }

    /**
     * Crea una factura de proveedor de una línea con el neto y el porcentaje de IRPF indicados (y su
     * proveedor si no se indica) y programa su borrado al terminar el test.
     *
     * @param float $net neto de la factura
     * @param float $irpf porcentaje de retención
     * @param Proveedor|null $supplier proveedor de la factura
     *
     * @return FacturaProveedor
     */
    protected function createSupplierInvoice(float $net, float $irpf, ?Proveedor $supplier = null): FacturaProveedor
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

        // creamos la tabla de pagos antes que la factura: al borrarla se borran sus recibos, que consultan sus
        // pagos dentro de una transacción, donde PostgreSQL no puede crear tablas
        new PagoProveedor();

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

    /**
     * Devuelve el ejercicio abierto de la empresa predeterminada para la fecha actual.
     *
     * @return Ejercicio
     */
    protected function getCurrentExercise(): Ejercicio
    {
        $exercise = new Ejercicio();
        $exercise->idempresa = Tools::settings('default', 'idempresa');
        $this->assertTrue($exercise->loadFromDate(Tools::date()));
        return $exercise;
    }

    /**
     * Devuelve el trimestre actual (T1-T4).
     *
     * @return string
     */
    protected function getCurrentPeriod(): string
    {
        return 'T' . ceil((int)date('n') / 3);
    }
}
