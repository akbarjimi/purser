<?php

declare(strict_types=1);

namespace Akbarjimi\ExcelImporter\Concerns;

use Akbarjimi\ExcelImporter\Enums\LogLevel;
use Illuminate\Support\Facades\Log;

trait LogsImportActivity
{
    protected function importLog(LogLevel $level, string $message, array $context = []): void
    {
        $channels = config('excel-importer.logging.channels', [config('logging.default', 'stack')]);

        if (! is_array($channels) || $channels === []) {
            $channels = ['stack'];
        }

        Log::stack($channels)->log(
            $level->value,
            $message,
            array_merge(['package' => 'excel-importer'], $context),
        );
    }
}
