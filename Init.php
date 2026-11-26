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

namespace FacturaScripts\Plugins\Modelo115;

use FacturaScripts\Core\Controller\ApiRoot;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Lib\AjaxForms\PurchasesHeaderHTML;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Dinamic\Model\AlbaranProveedor;
use FacturaScripts\Dinamic\Model\FacturaProveedor;
use FacturaScripts\Dinamic\Model\Inmueble180;
use FacturaScripts\Dinamic\Model\PedidoProveedor;
use FacturaScripts\Dinamic\Model\PresupuestoProveedor;

/**
 * Inicialización del plugin Modelo115: registra los endpoints de la API de sus dos modelos (115 y 180), la
 * pestaña de inmuebles del modelo 180 en la ficha del proveedor y el selector de inmueble en los documentos de
 * compra.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
final class Init extends InitClass
{
    /**
     * Registra los endpoints de solo lectura GET /api/3/modelo115 y GET /api/3/modelo180 (y los añade al
     * listado de recursos de la API), la pestaña de inmuebles del proveedor y el selector de inmueble de la
     * cabecera de las compras.
     */
    public function init(): void
    {
        Kernel::addRoute('/api/3/modelo115', 'ApiModelo115', -1);
        ApiRoot::addCustomResource('modelo115');

        Kernel::addRoute('/api/3/modelo180', 'ApiModelo180', -1);
        ApiRoot::addCustomResource('modelo180');

        $this->loadExtension(new Extension\Controller\EditProveedor());
        PurchasesHeaderHTML::addMod(new Mod\PurchasesHeaderHTMLMod());
    }

    /**
     * Se ejecuta al desinstalar el plugin. No hay nada que limpiar.
     */
    public function uninstall(): void
    {
    }

    /**
     * Se ejecuta al instalar o actualizar el plugin: crea la tabla de inmuebles antes que la columna
     * idinmueble180 de los documentos de compra, que apunta a ella.
     */
    public function update(): void
    {
        new Inmueble180();
        new PresupuestoProveedor();
        new PedidoProveedor();
        new AlbaranProveedor();
        new FacturaProveedor();
    }
}
