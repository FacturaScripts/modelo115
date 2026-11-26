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

use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Plugins\Modelo115\Lib\Modelo115;
use FacturaScripts\Plugins\Modelo115\Lib\Modelo115File;

/**
 * Pruebas del fichero de presentación del modelo 115 (Modelo115File): cabecera, posiciones de la página 01
 * según el diseño DR115e15v13, tipo de declaración, complementarias y validaciones.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo115FileTest extends Modelo115TestCase
{
    /**
     * Una complementaria (con importe a deducir) necesita el justificante de la anterior y lo escribe con la
     * marca X; la casilla 05 es la 03 menos la 04.
     */
    public function testComplementaria(): void
    {
        $exercise = $this->getCurrentExercise();
        $this->createSupplierInvoice(1000.0, 19.0);
        $company = $this->getCompany($exercise->idempresa);
        $result = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '', 40.0);

        $this->assertNotEmpty(Modelo115File::validate($result, $company, []));
        $options = ['justificante' => '1152026000001'];
        $this->assertSame([], Modelo115File::validate($result, $company, $options));

        $page = $this->page01(Modelo115File::generate($result, $company, $options));
        $this->assertSame(sprintf('%017d', (int)round($result['todeduct'] * 100)), substr($page, 157, 17));
        $this->assertSame(sprintf('%017d', (int)round(($result['retentions'] - 40.0) * 100)), substr($page, 174, 17));
        $this->assertSame('X', substr($page, 191, 1));
        $this->assertSame('1152026000001', substr($page, 192, 13));
    }

    /**
     * El fichero tiene la cabecera de 328 posiciones, la página 01 de 500 y el cierre, con las casillas en las
     * posiciones del diseño.
     */
    public function testFichero(): void
    {
        $exercise = $this->getCurrentExercise();
        $this->createSupplierInvoice(1000.0, 19.0);
        $company = $this->getCompany($exercise->idempresa);
        $period = $this->getCurrentPeriod();
        $result = Modelo115::generate($exercise->codejercicio, $period, '');
        $this->assertSame([], Modelo115File::validate($result, $company, []));

        $content = Modelo115File::generate($result, $company, []);
        $year = date('Y', strtotime($exercise->fechainicio));
        $id = '1150' . $year . Modelo115File::periodCode($period) . '0000>';
        $this->assertSame(328 + 500 + 18, strlen($content));
        $this->assertStringStartsWith('<T' . $id . '<AUX>', $content);
        $this->assertSame('</AUX>', substr($content, 322, 6));
        $this->assertStringEndsWith('</T' . $id, $content);

        $page = $this->page01($content);
        $this->assertSame('<T11501000>', substr($page, 0, 11));
        $this->assertSame(' ', substr($page, 11, 1));
        $this->assertSame('I', substr($page, 12, 1));
        $this->assertSame($year . Modelo115File::periodCode($period), substr($page, 102, 6));
        $this->assertSame(sprintf('%015d', $result['numrecipients']), substr($page, 108, 15));
        $this->assertSame(sprintf('%017d', (int)round($result['taxbase'] * 100)), substr($page, 123, 17));
        $this->assertSame(sprintf('%017d', (int)round($result['retentions'] * 100)), substr($page, 140, 17));
        $this->assertSame(str_repeat('0', 17), substr($page, 157, 17));
        $this->assertSame(sprintf('%017d', (int)round($result['result'] * 100)), substr($page, 174, 17));
        $this->assertSame(' ', substr($page, 191, 1));
        $this->assertSame('</T11501000>', substr($page, 488, 12));
    }

    /**
     * Validaciones: el anual no tiene fichero del 115, la domiciliación necesita IBAN y una negativa no puede
     * tener importe a ingresar.
     */
    public function testValidaciones(): void
    {
        $exercise = $this->getCurrentExercise();
        $this->createSupplierInvoice(1000.0, 19.0);
        $company = $this->getCompany($exercise->idempresa);

        $annual = Modelo115::generate($exercise->codejercicio, 'ANNUAL', '');
        $this->assertCount(1, Modelo115File::validate($annual, $company, []));

        $result = Modelo115::generate($exercise->codejercicio, $this->getCurrentPeriod(), '');
        $this->assertNotEmpty(Modelo115File::validate($result, $company, ['tipo' => 'U']));
        $iban = 'ES9121000418450200051332';
        $this->assertSame([], Modelo115File::validate($result, $company, ['tipo' => 'U', 'iban' => $iban]));
        $page = $this->page01(Modelo115File::generate($result, $company, ['tipo' => 'U', 'iban' => $iban]));
        $this->assertSame('U', substr($page, 12, 1));
        $this->assertSame(str_pad($iban, 34), substr($page, 205, 34));

        $this->assertNotEmpty(Modelo115File::validate($result, $company, ['tipo' => 'N']));
    }

    /**
     * Carga la empresa del ejercicio.
     *
     * @param int $idempresa empresa
     *
     * @return Empresa
     */
    private function getCompany(int $idempresa): Empresa
    {
        $company = new Empresa();
        $this->assertTrue($company->load($idempresa));
        if (empty($company->cifnif)) {
            $company->cifnif = 'B12345674';
        }
        return $company;
    }

    /**
     * Devuelve la página 01 del fichero (tras la cabecera de 328 posiciones).
     *
     * @param string $content contenido del fichero
     *
     * @return string
     */
    private function page01(string $content): string
    {
        $page = substr($content, 328, 500);
        $this->assertSame(500, strlen($page));
        return $page;
    }
}
