<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Akbarjimi\Purser\Contracts\RowHandler;
use Akbarjimi\Purser\DTOs\RowData;
use Akbarjimi\Purser\DTOs\StagedRow;
use Akbarjimi\Purser\Repositories\ExcelRowRepository;

final class SheetRowBuffer implements RowHandler
{
    /** @var list<StagedRow> */
    private array $buffer = [];

    private int $inserted = 0;

    public function __construct(
        private readonly int $sheetId,
        private readonly ExcelRowRepository $rowRepository,
        private readonly string $hashAlgo,
        private readonly int $batchSize,
    ) {}

    public function handle(RowData $row): void
    {
        $encoded = json_encode($row->cells, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->buffer[] = new StagedRow(
            sheetId: $this->sheetId,
            rowIndex: $row->rowNumber,
            content: $encoded,
            hashAlgo: $this->hashAlgo,
            contentHash: hash($this->hashAlgo, $encoded),
            createdAt: now()->toDateTimeString(),
            updatedAt: now()->toDateTimeString(),
        );

        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $this->rowRepository->bulkUpsert(
            array_map(static fn (StagedRow $row) => $row->toArray(), $this->buffer)
        );

        $this->inserted += count($this->buffer);
        $this->buffer = [];
    }

    public function inserted(): int
    {
        return $this->inserted;
    }
}
