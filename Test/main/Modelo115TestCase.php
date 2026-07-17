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

use FacturaScripts\Core\Model\User;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

class Modelo115TestCase extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    /**
     * Cleanup callbacks to run after each test in reverse order.
     *
     * @var array<int, callable>
     */
    private array $cleanupCallbacks = [];

    public function setUp(): void
    {
        // inicializamos los modelos para que se creen las
        // tablas necesarias y no de error de Foreign Key
        new User();

        self::setDefaultSettings();
        self::installAccountingPlan();
    }

    protected function addCleanup(callable $callback): void
    {
        $this->cleanupCallbacks[] = $callback;
    }

    protected function tearDown(): void
    {
        while ($callback = array_pop($this->cleanupCallbacks)) {
            try {
                $callback();
            } catch (\Throwable $th) {
                error_log($th->getMessage());
            }
        }

        $this->logErrors();
    }
}
