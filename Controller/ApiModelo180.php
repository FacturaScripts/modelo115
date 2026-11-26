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

namespace FacturaScripts\Plugins\Modelo115\Controller;

use FacturaScripts\Core\Response;
use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Dinamic\Lib\Modelo180Api;

/**
 * Endpoint de solo lectura de la API con los datos del modelo 180 (resumen anual de retenciones sobre
 * arrendamientos de inmuebles urbanos): GET /api/3/modelo180. Los trimestres tienen su propio endpoint,
 * GET /api/3/modelo115.
 *
 * Parámetros de la query string:
 *   - codejercicio: ejercicio. Si se indica, manda sobre idempresa y anio.
 *   - idempresa: empresa (por defecto, la predeterminada) y anio: año natural (por defecto, el actual).
 *   - periodo: opcional; solo ANUAL (también ANNUAL, Y, A y 0A), que es el valor por defecto.
 *   - detalle: 1/true para incluir las facturas. El detalle por arrendador e inmueble va siempre, con los
 *     mismos registros que el fichero del modelo 180.
 *   - codretencion: retención por la que filtrar las facturas (vacío = todas).
 *
 * Errores ({"status": "error", "message": ...}): 405 method-not-allowed, 400 invalid-period o invalid-year,
 * 404 exercise-not-found, company-not-found o retention-not-found.
 *
 * Una clave API sin acceso completo necesita el permiso de lectura del recurso «modelo180».
 *
 * No crea, modifica ni borra registros.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class ApiModelo180 extends ApiController
{
    /** Parámetros de la query string que acepta el endpoint. */
    private const PARAMS = ['codejercicio', 'idempresa', 'anio', 'periodo', 'detalle', 'codretencion'];

    /**
     * Valida el método, calcula el modelo con Modelo180Api y devuelve el resultado en JSON.
     */
    protected function runResource(): void
    {
        if ($this->request->method() !== 'GET') {
            $this->response
                ->setHttpCode(Response::HTTP_METHOD_NOT_ALLOWED)
                ->json(['status' => 'error', 'message' => 'method-not-allowed']);
            return;
        }

        $params = [];
        foreach (self::PARAMS as $key) {
            $params[$key] = $this->request->query($key);
        }

        $result = Modelo180Api::build($params);
        $this->response->setHttpCode($result['code'])->json($result['body']);
    }
}
