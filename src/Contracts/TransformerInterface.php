<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Contracts;

use Akbarjimi\Purser\Models\ExcelSheet;

interface TransformerInterface
{
    public function transform(array $mappedRow, ExcelSheet $sheet): array;
}
