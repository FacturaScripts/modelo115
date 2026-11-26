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

namespace FacturaScripts\Plugins\Modelo115\Model;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Modelo180;
use FacturaScripts\Dinamic\Model\Proveedor;

/**
 * Inmueble arrendado de un proveedor (arrendador), con los datos que pide el registro de perceptor del
 * modelo 180: situación, referencia catastral y dirección normalizada.
 *
 * Las facturas de proveedor pueden indicar su inmueble (columna idinmueble180). Si no lo indican, el
 * modelo 180 usa el único inmueble del proveedor o reparte la base entre sus inmuebles según el
 * porcentaje de cada uno.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
class Inmueble180 extends ModelClass
{
    use ModelTrait;

    /** Inmueble con referencia catastral en territorio común (fuera del País Vasco y Navarra). */
    const SITUACION_COMUN = 1;

    /** Inmueble con referencia catastral en Navarra. */
    const SITUACION_NAVARRA = 3;

    /** Inmueble con referencia catastral en el País Vasco. */
    const SITUACION_PAIS_VASCO = 2;

    /** Inmueble sin referencia catastral. */
    const SITUACION_SIN_REFERENCIA = 4;

    /** @var string bloque */
    public $bloque;

    /** @var string calificador del número (BIS, DUP, MOD, ANT...) */
    public $calificador;

    /** @var string código INE del municipio (5 dígitos) */
    public $codmunicipio;

    /** @var string código postal */
    public $codpostal;

    /** @var string proveedor (arrendador) al que pertenece el inmueble */
    public $codproveedor;

    /** @var string código de provincia del inmueble (2 dígitos, 53 = Isla de La Palma) */
    public $codprovincia;

    /** @var string complemento del domicilio (urbanización, centro comercial, local...) */
    public $complemento;

    /** @var string fecha y hora de creación */
    public $creation_date;

    /** @var string descripción libre para identificar el inmueble */
    public $descripcion;

    /** @var string escalera */
    public $escalera;

    /** @var int identificador */
    public $id;

    /** @var string fecha y hora de la última modificación */
    public $last_update;

    /** @var string localidad o población (solo si es distinta del municipio) */
    public $localidad;

    /** @var string municipio */
    public $municipio;

    /** @var string nombre de la vía pública */
    public $nombrevia;

    /** @var string número de casa o punto kilométrico */
    public $numero;

    /** @var string planta o piso */
    public $planta;

    /** @var float porcentaje de la base del proveedor que se asigna a este inmueble en el reparto */
    public $porcentaje;

    /** @var string portal */
    public $portal;

    /** @var string puerta */
    public $puerta;

    /** @var string referencia catastral */
    public $refcatastral;

    /** @var int situación del inmueble: 1 a 4 (ver constantes SITUACION_*) */
    public $situacion;

    /** @var string tipo de numeración: NUM, KM. o S/N */
    public $tiponumeracion;

    /** @var string código INE del tipo de vía (CL, AV, PZ...) */
    public $tipovia;

    /**
     * Valores por defecto de un inmueble nuevo.
     */
    public function clear(): void
    {
        parent::clear();
        $this->porcentaje = 0.0;
        $this->situacion = self::SITUACION_COMUN;
        $this->tiponumeracion = 'NUM';
    }

    /**
     * Devuelve el proveedor (arrendador) del inmueble.
     *
     * @return Proveedor
     */
    public function getSupplier(): Proveedor
    {
        $supplier = new Proveedor();
        $supplier->load($this->codproveedor);
        return $supplier;
    }

    /**
     * Crea la tabla del proveedor antes que la del inmueble, por la clave ajena.
     *
     * @return string
     */
    public function install(): string
    {
        new Proveedor();
        return parent::install();
    }

    /**
     * Devuelve los datos que faltan para declarar el inmueble en el modelo 180.
     *
     * @return string[] claves de traducción de los datos que faltan; vacío si está completo
     */
    public function missingData(): array
    {
        $missing = [];
        if ((int)$this->situacion !== self::SITUACION_SIN_REFERENCIA && empty($this->refcatastral)) {
            $missing[] = 'cadastral-reference';
        }
        if (empty($this->nombrevia)) {
            $missing[] = 'street-name';
        }
        if (empty($this->municipio)) {
            $missing[] = 'municipality';
        }
        if (empty($this->codprovincia)) {
            $missing[] = 'province';
        }
        if (empty($this->codpostal)) {
            $missing[] = 'zip-code';
        }
        return $missing;
    }

    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Nombre corto del inmueble para listados: la descripción o, si no tiene, la dirección.
     *
     * @return string
     */
    public function primaryDescription(): string
    {
        if (!empty($this->descripcion)) {
            return $this->descripcion;
        }

        $address = trim(implode(' ', array_filter([$this->tipovia, $this->nombrevia, $this->numero])));
        return $address === '' ? '#' . $this->id : $address;
    }

    public static function tableName(): string
    {
        return 'modelo180_inmuebles';
    }

    /**
     * Limpia y valida los datos: situación entre 1 y 4, referencia catastral obligatoria salvo en la
     * situación 4, porcentaje entre 0 y 100, códigos numéricos y provincia calculada del código postal si
     * no se indica.
     *
     * @return bool
     */
    public function test(): bool
    {
        $this->creation_date = $this->creation_date ?? Tools::dateTime();

        // limpiamos los textos y pasamos a mayúsculas los códigos
        foreach (['bloque', 'calificador', 'complemento', 'descripcion', 'escalera', 'localidad', 'municipio',
                     'nombrevia', 'numero', 'planta', 'portal', 'puerta', 'tiponumeracion', 'tipovia'] as $field) {
            $this->{$field} = Tools::noHtml(trim((string)$this->{$field}));
        }
        $this->refcatastral = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$this->refcatastral));
        $this->tipovia = strtoupper($this->tipovia);
        $this->tiponumeracion = strtoupper($this->tiponumeracion);
        $this->codmunicipio = preg_replace('/\D/', '', (string)$this->codmunicipio);
        $this->codpostal = preg_replace('/\D/', '', (string)$this->codpostal);
        $this->codprovincia = preg_replace('/\D/', '', (string)$this->codprovincia);

        if (empty($this->codproveedor)) {
            Tools::log()->warning('field-can-not-be-null', ['%fieldName%' => 'codproveedor', '%tableName%' => static::tableName()]);
            return false;
        }

        // situación del inmueble
        $this->situacion = (int)$this->situacion;
        if ($this->situacion < self::SITUACION_COMUN || $this->situacion > self::SITUACION_SIN_REFERENCIA) {
            Tools::log()->warning('invalid-property-situation');
            return false;
        }

        // la referencia catastral es obligatoria salvo en la situación 4, que va sin ella
        if ($this->situacion === self::SITUACION_SIN_REFERENCIA) {
            $this->refcatastral = '';
        } elseif (empty($this->refcatastral)) {
            Tools::log()->warning('cadastral-reference-required');
            return false;
        } elseif ($this->situacion === self::SITUACION_COMUN && strlen($this->refcatastral) !== 20) {
            Tools::log()->warning('invalid-cadastral-reference');
            return false;
        }

        // porcentaje de reparto
        $this->porcentaje = (float)$this->porcentaje;
        if ($this->porcentaje < 0 || $this->porcentaje > 100) {
            Tools::log()->warning('invalid-percentage');
            return false;
        }

        // códigos numéricos de longitud fija
        if ($this->codpostal !== '' && strlen($this->codpostal) !== 5) {
            Tools::log()->warning('invalid-zip-code');
            return false;
        }
        if ($this->codmunicipio !== '' && strlen($this->codmunicipio) !== 5) {
            Tools::log()->warning('invalid-municipality-code');
            return false;
        }

        // provincia: la indicada o la del código postal
        if ($this->codprovincia === '') {
            $this->codprovincia = Modelo180::provinceCode($this->codpostal, '');
        }
        if ($this->codprovincia !== '' && false === Modelo180::isProvinceCode($this->codprovincia)) {
            Tools::log()->warning('invalid-province-code');
            return false;
        }

        return parent::test();
    }

    /**
     * El listado de los inmuebles es la pestaña de la ficha del proveedor.
     *
     * @param string $type
     * @param string $list
     *
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'ListProveedor'): string
    {
        if ('list' === $type && !empty($this->codproveedor)) {
            return $this->getSupplier()->url() . '&activetab=ListInmueble180';
        }

        return parent::url($type, $list);
    }

    /**
     * Guarda la fecha de la última modificación.
     *
     * @return bool
     */
    protected function saveUpdate(): bool
    {
        $this->last_update = Tools::dateTime();
        return parent::saveUpdate();
    }
}
