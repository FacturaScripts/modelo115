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

namespace FacturaScripts\Plugins\Modelo115\Mod;

use FacturaScripts\Core\Contract\PurchasesModInterface;
use FacturaScripts\Core\Model\Base\PurchaseDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Inmueble180;

/**
 * Añade al detalle de la cabecera de los documentos de compra el selector del inmueble arrendado del
 * modelo 180. Solo aparece si el proveedor tiene inmuebles; si se deja en automático, el modelo 180 usa el
 * único inmueble del proveedor o reparte la base entre sus inmuebles.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class PurchasesHeaderHTMLMod implements PurchasesModInterface
{
    /** Nombre del campo del formulario y de la columna del documento. */
    const FIELD = 'idinmueble180';

    public function apply(PurchaseDocument &$model, array $formData): void
    {
        // si se cambia de proveedor, quitamos el inmueble si es de otro
        if (empty($model->{self::FIELD})) {
            return;
        }

        $property = new Inmueble180();
        if (false === $property->load($model->{self::FIELD}) || $property->codproveedor !== $model->codproveedor) {
            $model->{self::FIELD} = null;
        }
    }

    /**
     * Guarda el inmueble elegido. Se aplica antes que el resto para capturarlo también en los documentos
     * nuevos. Solo cambia si el formulario trae el campo.
     *
     * @param PurchaseDocument $model
     * @param array $formData
     */
    public function applyBefore(PurchaseDocument &$model, array $formData): void
    {
        if (array_key_exists(self::FIELD, $formData)) {
            $model->{self::FIELD} = empty($formData[self::FIELD]) ? null : (int)$formData[self::FIELD];
        }
    }

    public function assets(): void
    {
    }

    public function newBtnFields(): array
    {
        return [];
    }

    public function newFields(): array
    {
        return [];
    }

    public function newModalFields(): array
    {
        return ['inmueble180'];
    }

    public function renderField(PurchaseDocument $model, string $field): ?string
    {
        if ($field === 'inmueble180') {
            return self::property($model);
        }

        return null;
    }

    /**
     * Selector de inmueble con los del proveedor del documento. Vacío si el proveedor no tiene ninguno.
     *
     * @param PurchaseDocument $model
     *
     * @return string
     */
    private static function property(PurchaseDocument $model): string
    {
        if (empty($model->codproveedor)) {
            return '';
        }

        $where = [Where::eq('codproveedor', $model->codproveedor)];
        $properties = Inmueble180::all($where, ['id' => 'ASC'], 0, 0);
        if (empty($properties)) {
            return '';
        }

        $options = '<option value="">' . Tools::trans('model-180-automatic') . '</option>';
        foreach ($properties as $property) {
            $selected = (int)$model->{self::FIELD} === (int)$property->id ? ' selected' : '';
            $options .= '<option value="' . $property->id . '"' . $selected . '>'
                . Tools::noHtml($property->primaryDescription()) . '</option>';
        }

        $attributes = $model->editable ? 'name="' . self::FIELD . '"' : 'disabled';
        return '<div class="col-sm-6">'
            . '<div class="mb-2">'
            . '<span title="' . Tools::trans('model-180-property-p') . '">' . Tools::trans('model-180-property') . '</span>'
            . '<select ' . $attributes . ' class="form-select">' . $options . '</select>'
            . '</div>'
            . '</div>';
    }
}
