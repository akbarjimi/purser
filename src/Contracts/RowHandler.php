<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Contracts;

use Akbarjimi\Purser\DTOs\RowData;

interface RowHandler
{
    public function handle(RowData $row): void;
}
