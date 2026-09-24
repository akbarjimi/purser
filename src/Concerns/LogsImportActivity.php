<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Concerns;

use Akbarjimi\Purser\Enums\LogLevel;
use Illuminate\Support\Facades\Log;

trait LogsImportActivity
{
    protected function importLog(LogLevel $level, string $message, array $context = []): void
    {
        $channels = config('purser.logging.channels', [config('logging.default', 'stack')]);

        if (! is_array($channels) || $channels === []) {
            $channels = ['stack'];
        }

        Log::stack($channels)->log(
            $level->value,
            $message,
            array_merge(['package' => 'purser'], $context),
        );
    }
}
