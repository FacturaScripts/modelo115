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

use FacturaScripts\Plugins\Modelo115\Lib\Modelo180Api;

/**
 * Pruebas de la respuesta del endpoint GET /api/3/modelo180 (Modelo180Api): datos, resumen, casillas, detalle
 * por arrendador e inmueble, periodo, errores y avisos.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Modelo180ApiTest extends Modelo115TestCase
{
    /**
     * Con detalle=1 añade las facturas al detalle por arrendador e inmueble.
     */
    public function testDetalle(): void
    {
        $exercise = $this->getCurrentExercise();
        $invoice = $this->createSupplierInvoice(1000.0, 19.0);

        $data = $this->callApi(['codejercicio' => $exercise->codejercicio, 'detalle' => '1']);
        $this->assertArrayHasKey('perceptores', $data['detalle']);
        $this->assertArrayHasKey('facturas', $data['detalle']);
        $codes = array_column($data['detalle']['facturas'], 'codigo');
        $this->assertContains($invoice->codigo, $codes);
    }

    /**
     * Errores de los parámetros: periodo distinto del anual, retención, ejercicio, empresa y año.
     */
    public function testErrores(): void
    {
        $exercise = $this->getCurrentExercise();

        $code = 0;
        foreach (['T1', '2T', '03', 'XX'] as $period) {
            $data = $this->callApi(['codejercicio' => $exercise->codejercicio, 'periodo' => $period], $code);
            $this->assertSame(400, $code, $period);
            $this->assertSame('invalid-period', $data['message']);
            $this->assertSame(['ANUAL'], $data['allowed']);
        }

        $data = $this->callApi(['codejercicio' => $exercise->codejercicio, 'codretencion' => 'NO-EXISTE'], $code);
        $this->assertSame(404, $code);
        $this->assertSame('retention-not-found', $data['message']);

        $data = $this->callApi(['codejercicio' => 'NO-EXISTE'], $code);
        $this->assertSame(404, $code);
        $this->assertSame('exercise-not-found', $data['message']);

        $data = $this->callApi(['idempresa' => '999999'], $code);
        $this->assertSame(404, $code);
        $this->assertSame('company-not-found', $data['message']);

        $data = $this->callApi(['anio' => '20X6'], $code);
        $this->assertSame(400, $code);
        $this->assertSame('invalid-year', $data['message']);
    }

    /**
     * Devuelve el modelo 180, su resumen, sus casillas y siempre el detalle por perceptor: un registro por
     * arrendador e inmueble (aquí, sin inmueble: situación 4 y un aviso). Es informativo: resultado vale null.
     */
    public function testRespuesta(): void
    {
        $exercise = $this->getCurrentExercise();
        $invoice1 = $this->createSupplierInvoice(1000.0, 19.0);
        $this->createSupplierInvoice(500.0, 19.0, $invoice1->getSubject());

        $code = 0;
        $data = $this->callApi(['codejercicio' => $exercise->codejercicio, 'periodo' => 'annual'], $code);
        $this->assertSame(200, $code);
        $this->assertSame('ANUAL', $data['periodo']);
        $this->assertSame('180', $data['modelo']);
        $this->assertArrayNotHasKey('modelo_anual', $data);
        $this->assertNull($data['resultado']);
        $this->assertSame(['codretencion' => ''], $data['parametros']);

        $year = date('Y', strtotime($exercise->fechainicio));
        $this->assertSame($year . '-01-01', $data['fechainicio']);
        $this->assertSame($year . '-12-31', $data['fechafin']);

        // detalle por perceptor sin pedirlo, pero no el de facturas
        $this->assertArrayHasKey('perceptores', $data['detalle']);
        $this->assertArrayNotHasKey('facturas', $data['detalle']);

        $found = null;
        foreach ($data['detalle']['perceptores'] as $recipient) {
            if ($recipient['codproveedor'] === $invoice1->codproveedor) {
                $found = $recipient;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame(2, $found['num_facturas']);
        $this->assertSame(1500.0, $found['base']);
        $this->assertSame(285.0, $found['retenciones']);
        $this->assertSame(19.0, $found['porcentaje']);
        $this->assertSame(1, $found['modalidad']);
        $this->assertSame(0, $found['ejercicio_devengo']);
        $this->assertSame(4, $found['situacion']);
        $this->assertNull($found['inmueble']);
        $this->assertCount(2, $found['facturas']);

        // resumen del 180: suma de los registros, y las casillas 01-03 con los mismos totales
        $this->assertSame(['num_registros', 'base', 'retenciones', 'num_facturas'], array_keys($data['resumen']));
        $this->assertSame(count($data['detalle']['perceptores']), $data['resumen']['num_registros']);
        $base = 0.0;
        $retentions = 0.0;
        foreach ($data['detalle']['perceptores'] as $recipient) {
            $base += $recipient['base'];
            $retentions += $recipient['retenciones'];
        }
        $this->assertEqualsWithDelta($base, $data['resumen']['base'], 0.001);
        $this->assertEqualsWithDelta($retentions, $data['resumen']['retenciones'], 0.001);
        $this->assertSame([
            '01' => $data['resumen']['num_registros'],
            '02' => $data['resumen']['base'],
            '03' => $data['resumen']['retenciones'],
        ], $data['casillas']);
        $this->assertTrue($this->hasWarning($data, 'no tiene inmuebles del modelo 180'));
    }

    /**
     * El detalle de un arrendador con inmueble incluye los datos del inmueble.
     */
    public function testConInmueble(): void
    {
        $exercise = $this->getCurrentExercise();
        $invoice = $this->createSupplierInvoice(1000.0, 19.0);
        $property = $this->createProperty($invoice->getSubject());

        $data = $this->callApi(['codejercicio' => $exercise->codejercicio]);
        $found = null;
        foreach ($data['detalle']['perceptores'] as $recipient) {
            if ($recipient['codproveedor'] === $invoice->codproveedor) {
                $found = $recipient;
            }
        }
        $this->assertNotNull($found);
        $this->assertSame(1, $found['situacion']);
        $this->assertFalse($found['reparto']);
        $this->assertSame((int)$property->id, $found['inmueble']['idinmueble']);
        $this->assertSame($property->refcatastral, $found['inmueble']['refcatastral']);
        $this->assertSame('28', $found['inmueble']['codprovincia']);
        $this->assertSame('28001', $found['inmueble']['codpostal']);
    }

    /**
     * Llama a Modelo180Api::build() y devuelve el cuerpo de la respuesta.
     *
     * @param array $params parámetros de la petición
     * @param int|null $code código HTTP de la respuesta
     *
     * @return array
     */
    private function callApi(array $params, ?int &$code = null): array
    {
        $result = Modelo180Api::build($params);
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
