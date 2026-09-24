<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ExcelFileRegistered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $excelFileId) {}
}
