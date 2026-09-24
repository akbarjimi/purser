<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Contracts;

use Akbarjimi\Purser\DTOs\SheetInfo;
use Akbarjimi\Purser\Models\ExcelFile;

interface SheetDiscoveryInterface
{
    /**
     * @return list<SheetInfo>
     */
    public function discover(ExcelFile $file): array;
}
