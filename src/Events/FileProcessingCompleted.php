<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class FileProcessingCompleted
{
    use Dispatchable;

    public function __construct(public readonly int $fileId) {}
}
