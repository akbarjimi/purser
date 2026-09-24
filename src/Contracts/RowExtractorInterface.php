<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Contracts;

use Akbarjimi\Purser\Models\ExcelSheet;

interface RowExtractorInterface
{
    public function extract(ExcelSheet $sheet): int;
}
