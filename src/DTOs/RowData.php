<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\DTOs;

final class RowData
{
    public function __construct(
        public readonly array $cells,
        public readonly int $rowNumber, // 0-based row number
    ) {}
}
