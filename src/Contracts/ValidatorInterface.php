<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Contracts;

use Akbarjimi\Purser\Models\ExcelSheet;

interface ValidatorInterface
{
    public function apply(array $payload, ExcelSheet $sheet): array;
}
