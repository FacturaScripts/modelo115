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

namespace FacturaScripts\Plugins\Modelo115\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\Modelo115;
use FacturaScripts\Dinamic\Model\Ejercicio;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Retencion;

/**
 * Código común de las respuestas de la API del plugin: Modelo115Api (GET /api/3/modelo115) y Modelo180Api
 * (GET /api/3/modelo180). Resuelve la empresa, el ejercicio y la retención de la petición, normaliza el periodo,
 * da formato a fechas e importes y construye los bloques que comparten los dos modelos (empresa, ejercicio,
 * facturas y avisos).
 *
 * Es un trait y no una clase base para que no se despliegue en Dinamic/Lib, que es común a todos los plugins.
 *
 * @author Daniel Fernández Giménez <contacto@danielfg.es>
 */
trait Modelo115ApiTrait
{
    /**
     * Normaliza el periodo recibido: mayúsculas y sin espacios. Convierte 1T-4T en T1-T4, 1-12 en 01-12 y
     * ANNUAL, Y, A y 0A en ANUAL. Los valores que no reconoce se devuelven normalizados sin más.
     *
     * @param string $period periodo recibido
     *
     * @return string periodo normalizado
     */
    public static function normalizePeriod(string $period): string
    {
        $value = strtoupper(preg_replace('/\s+/', '', $period));
        if (preg_match('/^T([1-4])$/', $value, $matches) || preg_match('/^([1-4])T$/', $value, $matches)) {
            return 'T' . $matches[1];
        }

        if (in_array($value, ['ANUAL', 'ANNUAL', 'Y', 'A', '0A'], true)) {
            return 'ANUAL';
        }

        if (preg_match('/^(0?[1-9]|1[0-2])$/', $value)) {
            return sprintf('%02d', (int)$value);
        }

        return $value;
    }

    /**
     * Comprueba el parámetro codretencion: vacío (todas las retenciones) o el código de una retención que exista.
     *
     * @param array $params parámetros de la petición
     *
     * @return array ['codretencion' => código normalizado] o ['error' => respuesta de error]
     */
    protected static function checkRetention(array $params): array
    {
        $codretencion = trim((string)($params['codretencion'] ?? ''));
        if ($codretencion !== '' && false === (new Retencion())->load($codretencion)) {
            return ['error' => static::error(404, 'retention-not-found', ['codretencion' => $codretencion])];
        }

        return ['codretencion' => $codretencion];
    }

    /**
     * Devuelve los datos básicos de la empresa para la respuesta.
     *
     * @param Empresa $company empresa declarante
     *
     * @return array
     */
    protected static function companyData(Empresa $company): array
    {
        return [
            'idempresa' => (int)$company->idempresa,
            'nombre' => $company->nombre,
            'cifnif' => $company->cifnif,
        ];
    }

    /**
     * Prepara una respuesta de error en el formato de los endpoints del core.
     *
     * @param int $code código HTTP del error
     * @param string $message clave del error
     * @param array $extra datos adicionales para la respuesta
     *
     * @return array ['code' => código HTTP, 'body' => cuerpo de la respuesta]
     */
    protected static function error(int $code, string $message, array $extra = []): array
    {
        return ['code' => $code, 'body' => array_merge(['status' => 'error', 'message' => $message], $extra)];
    }

    /**
     * Devuelve los datos básicos del ejercicio para la respuesta.
     *
     * @param Ejercicio $exercise ejercicio del modelo
     *
     * @return array
     */
    protected static function exerciseData(Ejercicio $exercise): array
    {
        return [
            'codejercicio' => $exercise->codejercicio,
            'nombre' => $exercise->nombre,
            'fechainicio' => static::isoDate($exercise->fechainicio),
            'fechafin' => static::isoDate($exercise->fechafin),
            'estado' => $exercise->estado,
        ];
    }

    /**
     * Devuelve las facturas del modelo como filas planas.
     *
     * @param array $result resultados de Modelo115::generate()
     *
     * @return array
     */
    protected static function getInvoices(array $result): array
    {
        $list = [];
        foreach ($result['invoices'] as $invoice) {
            $list[] = [
                'idfactura' => (int)$invoice->idfactura,
                'codigo' => (string)$invoice->codigo,
                'fecha' => static::isoDate($invoice->fecha),
                'codproveedor' => (string)$invoice->codproveedor,
                'nombre' => (string)$invoice->nombre,
                'cifnif' => (string)$invoice->cifnif,
                'neto' => static::money($invoice->neto),
                'irpf' => static::money($invoice->irpf),
                'totalirpf' => static::money($invoice->totalirpf),
                'total' => static::money($invoice->total),
            ];
        }
        return $list;
    }

    /**
     * Devuelve los avisos para el usuario sobre los datos del modelo.
     *
     * @param Ejercicio $exercise ejercicio del modelo
     * @param array $result resultados de Modelo115::generate()
     * @param array $result180 resultados de Modelo180::build() (solo en el modelo 180)
     *
     * @return array textos en español
     */
    protected static function getWarnings(Ejercicio $exercise, array $result, array $result180 = []): array
    {
        $warnings = [];
        if (false === Modelo115::hasRentalSubaccounts($exercise->codejercicio)) {
            $warnings[] = 'El ejercicio ' . $exercise->codejercicio . ' no tiene subcuentas de retenciones de'
                . ' alquileres (cuenta especial IRPFA): se incluyen todas las facturas de proveedor con IRPF,'
                . ' también las de profesionales, que van al modelo 111.';
        } else {
            // con subcuentas IRPFA, las facturas sin asiento no se pueden clasificar y se mantienen
            $withoutEntry = 0;
            foreach ($result['invoices'] as $invoice) {
                if (empty($invoice->idasiento)) {
                    $withoutEntry++;
                }
            }
            if ($withoutEntry > 0) {
                $warnings[] = 'Hay ' . $withoutEntry . ' facturas de proveedor con IRPF sin asiento contable: no se'
                    . ' puede saber si son de alquileres y se incluyen en el modelo.';
            }
        }

        if (empty($result['invoices'])) {
            $warnings[] = 'No hay facturas de proveedor con retenciones de alquileres en el periodo.';
        }

        // avisos del modelo 180: datos de perceptores e inmuebles que faltan o repartos
        $translator = Tools::lang('es_ES');
        foreach ($result180['warnings'] ?? [] as $warning) {
            $warnings[] = $translator->trans($warning['key'], $warning['params']);
        }

        return $warnings;
    }

    /**
     * Convierte una fecha a formato ISO (Y-m-d).
     *
     * @param string|null $date fecha en cualquier formato reconocido por strtotime()
     *
     * @return string|null
     */
    protected static function isoDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $time = strtotime($date);
        return false === $time ? null : date('Y-m-d', $time);
    }

    /**
     * Redondea un importe a dos decimales.
     *
     * @param mixed $value importe
     *
     * @return float
     */
    protected static function money($value): float
    {
        return round((float)$value, 2) + 0.0;
    }

    /**
     * Interpreta un parámetro booleano: 1, true, yes, si, sí y on son verdaderos.
     *
     * @param mixed $value valor recibido
     *
     * @return bool
     */
    protected static function parseBool($value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'si', 'sí', 'on'], true);
    }

    /**
     * Interpreta un parámetro numérico, admitiendo la coma como separador decimal.
     *
     * @param mixed $value valor recibido
     *
     * @return float|null el número, 0 si viene vacío o null si no es un número
     */
    protected static function parseFloat($value): ?float
    {
        $text = str_replace(',', '.', trim((string)$value));
        if ($text === '') {
            return 0.0;
        }

        return is_numeric($text) ? (float)$text : null;
    }

    /**
     * Devuelve la versión del plugin leída de su facturascripts.ini.
     *
     * @return float|null
     */
    protected static function pluginVersion(): ?float
    {
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'facturascripts.ini';
        if (false === file_exists($file)) {
            return null;
        }

        $ini = parse_ini_file($file);
        return isset($ini['version']) ? (float)$ini['version'] : null;
    }

    /**
     * Busca el ejercicio de la petición: por su código si se indica o, si no, por la empresa (la de la
     * petición o la predeterminada) y el año (el de la petición o el actual).
     *
     * @param array $params parámetros de la petición: codejercicio, idempresa y anio
     *
     * @return array ['exercise' => Ejercicio, 'company' => Empresa] o ['error' => respuesta de error]
     */
    protected static function resolveExercise(array $params): array
    {
        $exercise = new Ejercicio();
        $company = new Empresa();

        $codejercicio = trim((string)($params['codejercicio'] ?? ''));
        if ($codejercicio !== '') {
            if (false === $exercise->load($codejercicio)) {
                return ['error' => static::error(404, 'exercise-not-found', ['codejercicio' => $codejercicio])];
            }
            if (false === $company->load($exercise->idempresa)) {
                return ['error' => static::error(404, 'company-not-found', ['idempresa' => (int)$exercise->idempresa])];
            }
            return ['exercise' => $exercise, 'company' => $company];
        }

        // empresa: la indicada o la predeterminada
        $idempresa = trim((string)($params['idempresa'] ?? ''));
        if ($idempresa === '') {
            $idempresa = (string)Tools::settings('default', 'idempresa', '');
        }
        if (false === ctype_digit($idempresa) || false === $company->load((int)$idempresa)) {
            $extra = ['idempresa' => ctype_digit($idempresa) ? (int)$idempresa : $idempresa];
            return ['error' => static::error(404, 'company-not-found', $extra)];
        }

        // año: el indicado o el actual
        $year = trim((string)($params['anio'] ?? ''));
        if ($year === '') {
            $year = date('Y');
        }
        if (false === (bool)preg_match('/^\d{4}$/', $year)) {
            return ['error' => static::error(400, 'invalid-year', ['anio' => $year])];
        }

        // ejercicio de la empresa cuya fecha de inicio cae en ese año
        $where = [
            Where::eq('idempresa', $company->idempresa),
            Where::gte('fechainicio', $year . '-01-01'),
            Where::lte('fechainicio', $year . '-12-31'),
        ];
        if (false === $exercise->loadWhere($where, ['fechainicio' => 'ASC'])) {
            $extra = ['idempresa' => (int)$company->idempresa, 'anio' => (int)$year];
            return ['error' => static::error(404, 'exercise-not-found', $extra)];
        }

        return ['exercise' => $exercise, 'company' => $company];
    }
}
