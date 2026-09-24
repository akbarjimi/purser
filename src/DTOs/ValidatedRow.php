<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\DTOs;

final class ValidatedRow
{
    public function __construct(
        public readonly int $rowIndex,
        public readonly array $data,
    ) {}
}
