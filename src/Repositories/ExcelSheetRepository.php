<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Repositories;

use Akbarjimi\Purser\Concerns\HasStatusTransitions;
use Akbarjimi\Purser\DTOs\SheetInfo;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Exceptions\Sheet\EmptySheetException;
use Akbarjimi\Purser\Models\ExcelSheet;
use Illuminate\Support\Collection;

final class ExcelSheetRepository
{
    use HasStatusTransitions;

    public function bulkCreate(int $fileId, array $sheets): void
    {
        if (empty($sheets)) {
            throw EmptySheetException::forFile($fileId);
        }

        $now = now();

        $rows = array_map(static fn (SheetInfo $sheet): array => [
            'excel_file_id' => $fileId,
            'name' => $sheet->name,
            'sheet_index' => $sheet->index,
            'total_rows' => $sheet->totalRows,
            'status' => ExcelSheetStatus::PENDING->value,
            'meta' => json_encode($sheet->raw, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ], $sheets);

        ExcelSheet::query()->upsert(
            $rows,
            uniqueBy: ['excel_file_id', 'sheet_index'],
            update: ['name', 'total_rows', 'meta', 'updated_at'],
        );
    }

    public function existsForFile(int $fileId): bool
    {
        return ExcelSheet::query()->where('excel_file_id', $fileId)->exists();
    }

    public function getByFileId(int $fileId): Collection
    {
        return ExcelSheet::query()
            ->where('excel_file_id', $fileId)
            ->orderBy('sheet_index')
            ->get();
    }

    public function getById(int $sheetId): ?ExcelSheet
    {
        return ExcelSheet::find($sheetId);
    }

    public function setChunkCount(int $sheetId, int $count): void
    {
        ExcelSheet::query()->where('id', $sheetId)->update(['chunk_count' => $count]);
    }

    public function markAsPending(int $sheetId): void
    {
        $this->markAs($sheetId, ExcelSheet::class, ExcelSheetStatus::PENDING);
    }

    public function markAsExtracting(int $sheetId): void
    {
        $this->markAs($sheetId, ExcelSheet::class, ExcelSheetStatus::EXTRACTING);
    }

    public function markAsExtracted(int $sheetId): void
    {
        $this->markAs($sheetId, ExcelSheet::class, ExcelSheetStatus::EXTRACTED, [
            'rows_extracted_at' => now(),
        ]);
    }

    public function markAsChunksDispatched(int $sheetId): void
    {
        $this->markAs($sheetId, ExcelSheet::class, ExcelSheetStatus::CHUNKS_DISPATCHED);
    }

    public function markAsCompleted(int $sheetId): void
    {
        $this->markAs($sheetId, ExcelSheet::class, ExcelSheetStatus::COMPLETED);
    }

    public function markAsFailed(int $sheetId, string $reason): void
    {
        $this->markAs($sheetId, ExcelSheet::class, ExcelSheetStatus::FAILED, [
            'error' => $reason,
        ]);
    }
}
