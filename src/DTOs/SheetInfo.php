<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\DTOs;

final class SheetInfo
{
    public function __construct(
        public readonly string $name,
        public readonly int $index,
        public readonly int $totalRows,
        public readonly int $totalColumns,
        public readonly array $raw = [],
    ) {}

    public static function fromPhpSpreadsheet(array $info, int $index): self
    {
        return new self(
            name: $info['worksheetName'] ?? "Sheet{$index}",
            index: $index,
            totalRows: (int) ($info['totalRows'] ?? 0),
            totalColumns: (int) ($info['totalColumns'] ?? 0),
            raw: $info,
        );
    }
}
