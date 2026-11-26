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

namespace FacturaScripts\Plugins\Modelo115\Extension\Controller;

use Closure;
use FacturaScripts\Core\Where;

/**
 * Añade a la ficha del proveedor la pestaña con sus inmuebles arrendados del modelo 180.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class EditProveedor
{
    /**
     * Crea la pestaña de inmuebles.
     *
     * @return Closure
     */
    public function createViews(): Closure
    {
        return function () {
            $this->addListView('ListInmueble180', 'Inmueble180', 'model-180-properties', 'fa-solid fa-building')
                ->addOrderBy(['id'], 'id', 1)
                ->addOrderBy(['descripcion'], 'description')
                ->addSearchFields(['descripcion', 'nombrevia', 'refcatastral', 'municipio']);
        };
    }

    /**
     * Carga los inmuebles del proveedor.
     *
     * @return Closure
     */
    public function loadData(): Closure
    {
        return function ($viewName, $view) {
            if ($viewName !== 'ListInmueble180') {
                return;
            }

            $codproveedor = $this->getViewModelValue($this->getMainViewName(), 'codproveedor');
            $view->loadData('', [Where::eq('codproveedor', $codproveedor)]);
        };
    }
}
