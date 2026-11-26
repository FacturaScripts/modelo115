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

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * Edición de un inmueble arrendado de un proveedor, con los datos del registro de perceptor del modelo 180.
 * Se llega desde la pestaña de inmuebles de la ficha del proveedor.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class EditInmueble180 extends EditController
{
    /**
     * @return string
     */
    public function getModelClassName(): string
    {
        return 'Inmueble180';
    }

    /**
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'purchases';
        $data['title'] = 'model-180-property';
        $data['icon'] = 'fa-solid fa-building';
        $data['showonmenu'] = false;
        return $data;
    }
}
