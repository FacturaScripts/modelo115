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
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Inmueble180;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\Modelo115\Lib\Modelo115;
use FacturaScripts\Plugins\Modelo115\Lib\Modelo180;
use FacturaScripts\Plugins\Modelo115\Lib\Modelo180File;
use FacturaScripts\Plugins\Modelo115\Mod\PurchasesHeaderHTMLMod;

/**
 * Pruebas del modelo 180: inmuebles, agrupación de las facturas por arrendador e inmueble (con reparto) y
 * fichero de presentación según el diseño de registro de la AEAT.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo180Test extends Modelo115TestCase
{
    /**
     * Solo bloquean la descarga del fichero los avisos de datos obligatorios (NIF, provincia, extranjero e
     * inmueble incompleto); los repartos, las bases negativas y los proveedores sin inmuebles solo informan.
     */
    public function testAvisosQueBloquean(): void
    {
        $warnings = [
            ['key' => 'model-180-supplier-without-nif', 'params' => ['%name%' => 'A']],
            ['key' => 'model-180-split', 'params' => ['%name%' => 'B', '%count%' => 2]],
            ['key' => 'model-180-supplier-without-province', 'params' => ['%name%' => 'C']],
            ['key' => 'model-180-negative-base', 'params' => ['%name%' => 'D']],
            ['key' => 'model-180-foreign-supplier', 'params' => ['%name%' => 'E']],
            ['key' => 'model-180-supplier-without-property', 'params' => ['%name%' => 'F']],
            ['key' => 'model-180-property-missing-data', 'params' => ['%name%' => 'G']],
            ['key' => 'model-180-invoice-other-property', 'params' => ['%code%' => 'H']],
        ];

        $blocking = Modelo180::blockingWarnings($warnings);
        $this->assertSame(
            ['model-180-supplier-without-nif', 'model-180-supplier-without-province', 'model-180-foreign-supplier',
                'model-180-property-missing-data'],
            array_column($blocking, 'key')
        );
        $this->assertSame([], Modelo180::blockingWarnings([]));

        // un proveedor sin inmuebles se declara en situación 4: no bloquea
        $invoice = $this->createSupplierInvoice(1000.0, 19.0);
        $result = Modelo180::build([$invoice]);
        $this->assertNotContains(
            'model-180-supplier-without-property',
            array_column(Modelo180::blockingWarnings($result['warnings']), 'key')
        );
    }

    /**
     * Códigos de provincia: del código postal (387xx es La Palma) o del nombre.
     */
    public function testCodigoProvincia(): void
    {
        $this->assertSame('28', Modelo180::provinceCode('28001', ''));
        $this->assertSame('38', Modelo180::provinceCode('38001', ''));
        $this->assertSame('53', Modelo180::provinceCode('38710', 'Santa Cruz de Tenerife'));
        $this->assertSame('01', Modelo180::provinceCode('', 'Álava'));
        $this->assertSame('15', Modelo180::provinceCode('', 'A Coruña'));
        $this->assertSame('07', Modelo180::provinceCode('', 'Illes Balears'));
        $this->assertSame('', Modelo180::provinceCode('99999', 'Atlantis'));
        $this->assertTrue(Modelo180::isProvinceCode('53'));
        $this->assertFalse(Modelo180::isProvinceCode('54'));
    }

    /**
     * Fichero completo: registros de 500 posiciones separados por CRLF, con los datos y totales en sus
     * posiciones del diseño de registro.
     */
    public function testFichero(): void
    {
        $invoice = $this->createSupplierInvoice(1000.0, 19.0);
        $supplier = $invoice->getSubject();
        $supplier->cifnif = '12345678Z';
        $supplier->razonsocial = 'Peñalver Núñez María';
        $this->assertTrue($supplier->save());
        $address = $supplier->getDefaultAddress();
        $address->codpostal = '08001';
        $this->assertTrue($address->save());
        $this->createProperty($supplier, ['planta' => '2', 'puerta' => 'B']);

        $company = new Empresa();
        $company->load(Tools::settings('default', 'idempresa'));
        $result = Modelo180::build([$invoice]);
        $content = Modelo180File::generate($company, 2026, $result['records'], [
            'justificante' => '1802026000123',
            'telefono' => '912345678',
            'contacto' => 'García López Ana',
        ]);

        $this->assertStringEndsWith("\r\n", $content);
        $lines = explode("\r\n", rtrim($content, "\r\n"));
        $this->assertCount(2, $lines);
        foreach ($lines as $line) {
            $this->assertSame(500, strlen($line));
        }

        // registro de tipo 1
        $type1 = $lines[0];
        $this->assertSame('1180' . '2026', substr($type1, 0, 8));
        $this->assertSame(Modelo180File::formatNif($company->cifnif), substr($type1, 8, 9));
        $this->assertSame('T', substr($type1, 57, 1));
        $this->assertSame('912345678', substr($type1, 58, 9));
        $this->assertSame('GARCIA LOPEZ ANA', rtrim(substr($type1, 67, 40)));
        $this->assertSame('1802026000123', substr($type1, 107, 13));
        $this->assertSame('  ', substr($type1, 120, 2));
        $this->assertSame('0000000000000', substr($type1, 122, 13));
        $this->assertSame('000000001', substr($type1, 135, 9));
        $this->assertSame(' 000000000100000', substr($type1, 144, 16));
        $this->assertSame('000000000019000', substr($type1, 160, 15));
        $this->assertSame(str_repeat(' ', 325), substr($type1, 175));

        // registro de tipo 2
        $type2 = mb_convert_encoding($lines[1], 'UTF-8', 'ISO-8859-1');
        $this->assertSame('21802026', substr($lines[1], 0, 8));
        $this->assertSame('12345678Z', substr($lines[1], 17, 9));
        $this->assertSame(str_repeat(' ', 9), substr($lines[1], 26, 9));
        $this->assertSame('PEÑALVER NUÑEZ MARIA', rtrim(mb_substr($type2, 35, 40)));
        $this->assertSame('08', substr($lines[1], 75, 2));
        $this->assertSame('1', substr($lines[1], 77, 1));
        $this->assertSame(' 0000000100000', substr($lines[1], 78, 14));
        $this->assertSame('1900', substr($lines[1], 92, 4));
        $this->assertSame('0000000019000', substr($lines[1], 96, 13));
        $this->assertSame('0000', substr($lines[1], 109, 4));
        $this->assertSame('1', substr($lines[1], 113, 1));
        $this->assertSame('9872023VH5797S0001WX', substr($lines[1], 114, 20));
        $this->assertSame('CL   ', substr($lines[1], 134, 5));
        $this->assertSame('MAYOR', rtrim(substr($lines[1], 139, 50)));
        $this->assertSame('NUM', substr($lines[1], 189, 3));
        $this->assertSame('12   ', substr($lines[1], 192, 5));
        $this->assertSame('2  ', substr($lines[1], 209, 3));
        $this->assertSame('B  ', substr($lines[1], 212, 3));
        $this->assertSame('MADRID', rtrim(substr($lines[1], 285, 30)));
        $this->assertSame('28079', substr($lines[1], 315, 5));
        $this->assertSame('28', substr($lines[1], 320, 2));
        $this->assertSame('28001', substr($lines[1], 322, 5));
        $this->assertSame(str_repeat(' ', 173), substr($lines[1], 327));
    }

    /**
     * Complementaria y sustitutiva: marca en la posición 121 o 122 y número de la declaración anterior.
     */
    public function testFicheroComplementaria(): void
    {
        $company = new Empresa();
        $company->load(Tools::settings('default', 'idempresa'));

        $content = Modelo180File::generate($company, 2026, [], ['tipo' => 'S', 'anterior' => '1802026000001']);
        $this->assertSame(' S1802026000001', substr($content, 120, 15));

        $content = Modelo180File::generate($company, 2026, [], ['tipo' => 'C', 'anterior' => '1802026000001']);
        $this->assertSame('C 1802026000001', substr($content, 120, 15));
    }

    /**
     * Formatos de los campos: textos en mayúsculas sin tildes (con Ñ y Ç de un byte), NIF, importes y
     * porcentajes.
     */
    public function testFormatos(): void
    {
        $this->assertSame("PEÑALVER O'DONNELL, JOSE & CIA. ÇA", Modelo180File::sanitize('Peñalver O&#39;Donnell, José & Cía.  ça'));
        $this->assertSame('CALLE MAYOR N 3 B', Modelo180File::sanitize('Calle <Mayor> nº3 € "B"'));

        $alpha = Modelo180File::formatAlpha('Peñalver', 10);
        $this->assertSame(10, strlen($alpha));
        $this->assertSame('PEÑALVER  ', mb_convert_encoding($alpha, 'UTF-8', 'ISO-8859-1'));
        $this->assertSame('ABC', Modelo180File::formatAlpha('abcdef', 3));

        $this->assertSame('12345678Z', Modelo180File::formatNif('12345678-z'));
        $this->assertSame('0B1234567', Modelo180File::formatNif('B1234567'));
        $this->assertSame('         ', Modelo180File::formatNif(''));

        $this->assertSame('0000000123456', Modelo180File::formatAmount(1234.56, 13));
        $this->assertSame('0000000123456', Modelo180File::formatAmount(-1234.56, 13));
        $this->assertSame('1900', Modelo180File::formatPercentage(19));
        $this->assertSame('0750', Modelo180File::formatPercentage(7.5));
        $this->assertSame('1800000000001', Modelo180File::formatNumber('180-0000000001', 13));
        $this->assertSame('1802026000001', Modelo180File::defaultReceipt(2026));
    }

    /**
     * Validaciones del inmueble: situación, referencia catastral, porcentaje, códigos y provincia calculada
     * del código postal.
     */
    public function testInmuebleValidaciones(): void
    {
        $supplier = $this->createSupplierInvoice(100.0, 19.0)->getSubject();

        $property = new Inmueble180();
        $property->codproveedor = $supplier->codproveedor;
        $property->refcatastral = '1234';
        $this->assertFalse($property->save(), 'referencia catastral corta en territorio común');

        $property->refcatastral = '9872023 VH5797S 0001 WX';
        $property->porcentaje = 150;
        $this->assertFalse($property->save(), 'porcentaje mayor de 100');

        $property->porcentaje = 50;
        $property->situacion = 7;
        $this->assertFalse($property->save(), 'situación no válida');

        $property->situacion = Inmueble180::SITUACION_COMUN;
        $property->codpostal = '38700';
        $this->assertTrue($property->save());
        $this->addCleanup(static function () use ($property) {
            $property->delete();
        });
        $this->assertSame('9872023VH5797S0001WX', $property->refcatastral);
        $this->assertSame('53', $property->codprovincia, 'Isla de La Palma por el código postal');

        // sin referencia catastral se vacía la referencia
        $property->situacion = Inmueble180::SITUACION_SIN_REFERENCIA;
        $this->assertTrue($property->save());
        $this->assertSame('', $property->refcatastral);

        // con referencia en el País Vasco no hace falta que tenga 20 caracteres
        $property->situacion = Inmueble180::SITUACION_PAIS_VASCO;
        $property->refcatastral = 'ABC123';
        $this->assertTrue($property->save());

        $property->codprovincia = '99';
        $this->assertFalse($property->save(), 'provincia no válida');
    }

    /**
     * Selector de inmueble de la cabecera de compras: solo con inmuebles del proveedor, guarda el elegido y
     * lo quita si es de otro proveedor.
     */
    public function testModCabeceraCompras(): void
    {
        $mod = new PurchasesHeaderHTMLMod();
        $invoice = $this->createSupplierInvoice(100.0, 19.0);
        $this->assertSame('', $mod->renderField($invoice, 'inmueble180'), 'sin inmuebles no hay selector');
        $this->assertNull($mod->renderField($invoice, 'otro-campo'));

        $property = $this->createProperty($invoice->getSubject(), ['descripcion' => 'Local centro']);
        $html = $mod->renderField($invoice, 'inmueble180');
        $this->assertStringContainsString('name="idinmueble180"', $html);
        $this->assertStringContainsString('value="' . $property->id . '"', $html);
        $this->assertStringContainsString('Local centro', $html);

        // guarda el inmueble del formulario y no lo toca si el formulario no lo trae
        $mod->applyBefore($invoice, ['idinmueble180' => (string)$property->id]);
        $mod->apply($invoice, []);
        $this->assertSame((int)$property->id, (int)$invoice->idinmueble180);
        $mod->applyBefore($invoice, []);
        $this->assertSame((int)$property->id, (int)$invoice->idinmueble180);

        // un inmueble de otro proveedor se quita
        $other = $this->createProperty($this->createSupplierInvoice(100.0, 19.0)->getSubject());
        $mod->applyBefore($invoice, ['idinmueble180' => (string)$other->id]);
        $mod->apply($invoice, []);
        $this->assertNull($invoice->idinmueble180);
    }

    /**
     * Con varios inmuebles, las facturas sin inmueble se reparten según el porcentaje (el último se queda
     * con el resto del redondeo) y las que lo indican van enteras a su inmueble.
     */
    public function testReparto(): void
    {
        $invoice = $this->createSupplierInvoice(1000.0, 19.0);
        $supplier = $invoice->getSubject();
        $local = $this->createProperty($supplier, ['porcentaje' => 75]);
        $garage = $this->createProperty($supplier, ['porcentaje' => 25, 'refcatastral' => '9872023VH5797S0002EM']);

        $records = $this->getSupplierRecords($supplier);
        $this->assertCount(2, $records);
        $this->assertSame((int)$local->id, $records[0]['idinmueble']);
        $this->assertSame(750.0, $records[0]['base']);
        $this->assertSame(142.5, $records[0]['retenciones']);
        $this->assertTrue($records[0]['reparto']);
        $this->assertSame((int)$garage->id, $records[1]['idinmueble']);
        $this->assertSame(250.0, $records[1]['base']);
        $this->assertSame(47.5, $records[1]['retenciones']);

        $result = Modelo180::build([$invoice]);
        $this->assertContains('model-180-split', array_column($result['warnings'], 'key'));

        // una factura con su inmueble va entera a él
        $second = $this->createSupplierInvoice(300.0, 19.0, $supplier);
        $second->idinmueble180 = $garage->id;
        $this->assertTrue($second->save());

        $records = $this->getSupplierRecords($supplier);
        $this->assertSame(750.0, $records[0]['base']);
        $this->assertSame(550.0, $records[1]['base']);
        $this->assertSame(104.5, $records[1]['retenciones']);
        $this->assertSame(1, $records[0]['numfacturas']);
        $this->assertSame(2, $records[1]['numfacturas']);
    }

    /**
     * Sin porcentajes, el reparto es a partes iguales y la suma cuadra con la factura.
     */
    public function testRepartoPartesIguales(): void
    {
        $invoice = $this->createSupplierInvoice(100.0, 19.0);
        $supplier = $invoice->getSubject();
        $this->createProperty($supplier);
        $this->createProperty($supplier, ['refcatastral' => '9872023VH5797S0002EM']);
        $this->createProperty($supplier, ['refcatastral' => '9872023VH5797S0003RQ']);

        $records = $this->getSupplierRecords($supplier);
        $this->assertCount(3, $records);
        $this->assertSame(33.33, $records[0]['base']);
        $this->assertSame(33.33, $records[1]['base']);
        $this->assertSame(33.34, $records[2]['base']);
        $this->assertEqualsWithDelta(19.0, array_sum(array_column($records, 'retenciones')), 0.001);
    }

    /**
     * Sin inmuebles, un registro sin referencia catastral (situación 4) y un aviso.
     */
    public function testSinInmueble(): void
    {
        $invoice = $this->createSupplierInvoice(1000.0, 19.0);

        $result = Modelo180::build([$invoice]);
        $this->assertCount(1, $result['records']);
        $this->assertNull($result['records'][0]['idinmueble']);
        $this->assertSame(Inmueble180::SITUACION_SIN_REFERENCIA, $result['records'][0]['situacion']);
        $this->assertContains('model-180-supplier-without-property', array_column($result['warnings'], 'key'));
    }

    /**
     * Un proveedor con un solo inmueble lleva todas sus facturas a ese inmueble.
     */
    public function testUnInmueble(): void
    {
        $invoice = $this->createSupplierInvoice(1000.0, 19.0);
        $supplier = $invoice->getSubject();
        $this->createSupplierInvoice(200.0, 19.0, $supplier);
        $property = $this->createProperty($supplier);

        $records = $this->getSupplierRecords($supplier);
        $this->assertCount(1, $records);
        $this->assertSame((int)$property->id, $records[0]['idinmueble']);
        $this->assertSame(1200.0, $records[0]['base']);
        $this->assertSame(228.0, $records[0]['retenciones']);
        $this->assertSame(19.0, $records[0]['porcentaje']);
        $this->assertSame(2, $records[0]['numfacturas']);
        $this->assertFalse($records[0]['reparto']);
        $this->assertSame('9872023VH5797S0001WX', $records[0]['refcatastral']);
    }

    /**
     * Registros del 180 del proveedor indicado, a partir de las facturas del año de Modelo115.
     *
     * @param Proveedor $supplier
     *
     * @return array
     */
    private function getSupplierRecords(Proveedor $supplier): array
    {
        $exercise = $this->getCurrentExercise();
        $result = Modelo115::generate($exercise->codejercicio, 'ANNUAL', '');
        $invoices = array_filter($result['invoices'], static function (FacturaProveedor $invoice) use ($supplier) {
            return $invoice->codproveedor === $supplier->codproveedor;
        });

        return Modelo180::build(array_values($invoices))['records'];
    }
}
