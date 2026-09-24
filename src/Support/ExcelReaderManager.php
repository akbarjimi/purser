<?php

namespace Akbarjimi\Purser\Support;

use Akbarjimi\Purser\Contracts\ExcelReaderDriver;
use Akbarjimi\Purser\Drivers\OpenSpoutDriver;
use Akbarjimi\Purser\Drivers\PhpSpreadsheetDriver;
use Akbarjimi\Purser\Exceptions\MissingDriverDependencyException;
use Illuminate\Support\Manager;

class ExcelReaderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('purser.driver', 'maatwebsite');
    }

    protected function createMaatwebsiteDriver(): ExcelReaderDriver
    {
        $this->ensureInstalled(\Maatwebsite\Excel\Excel::class, 'maatwebsite', 'maatwebsite/excel');

        return new PhpSpreadsheetDriver;
    }

    protected function createOpenspoutDriver(): ExcelReaderDriver
    {
        $this->ensureInstalled(\OpenSpout\Reader\XLSX\Reader::class, 'openspout', 'openspout/openspout');

        return new OpenSpoutDriver;
    }

    private function ensureInstalled(string $class, string $driver, string $package): void
    {
        if (! class_exists($class)) {
            throw MissingDriverDependencyException::for($driver, $package);
        }
    }
}
