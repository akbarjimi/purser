<?php

declare(strict_types=1);

namespace Akbarjimi\ExcelImporter\Repositories;

use Akbarjimi\ExcelImporter\Concerns\HasStatusTransitions;
use Akbarjimi\ExcelImporter\DTOs\ValidatedRow;
use Akbarjimi\ExcelImporter\Enums\ExcelRowStatus;
use Akbarjimi\ExcelImporter\Models\ExcelRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

final class ExcelRowRepository
{
    use HasStatusTransitions;

    public function bulkUpsert(array $rows, int $chunkSize = 500): void
    {
        collect($rows)
            ->chunk($chunkSize)
            ->each(function ($chunk) {
                $sanitized = $chunk->map(fn ($row) => array_diff_key($row, ['id' => null]))->all();
                DB::table('excel_rows')->upsert(
                    $sanitized,
                    ['excel_sheet_id', 'row_index'],
                    ['content', 'content_hash', 'hash_algo', 'status', 'updated_at'],
                );
            });
    }

    public function bulkUpdate(array $rows, int $chunkSize = 500): void
    {
        if ($rows === []) {
            return;
        }

        collect($rows)
            ->chunk($chunkSize)
            ->each(function ($chunk) {
                foreach ($chunk as $row) {
                    if (!isset($row['id'])) {
                        continue;
                    }

                    DB::table('excel_rows')
                        ->where('id', $row['id'])
                        ->update([
                            'content' => $row['content'],
                            'status' => $row['status'],
                            'row_index' => $row['row_index'] ?? null,
                            'updated_at' => $row['updated_at'] ?? now(),
                        ]);
                }
            });
    }

    public function getRowsBetween(int $sheetId, int $fromRowId, int $toRowId): LazyCollection
    {
        return ExcelRow::query()
            ->where('excel_sheet_id', $sheetId)
            ->whereBetween('id', [$fromRowId, $toRowId])
            ->orderBy('id')
            ->lazy();
    }

    public function getValidatedRowsForFile(int $fileId): LazyCollection
    {
        return ExcelRow::query()
            ->whereHas('excelSheet', fn ($q) => $q->where('excel_file_id', $fileId))
            ->where('status', ExcelRowStatus::VALIDATED->value)
            ->orderBy('id')
            ->lazy()
            ->map(fn (ExcelRow $row) => new ValidatedRow(
                rowIndex: $row->row_index,
                data: $row->content,
            ));
    }

    public function chunkRowIdsBySheet(int $sheetId, int $chunkSize, callable $callback): void
    {
        ExcelRow::query()
            ->where('excel_sheet_id', $sheetId)
            ->orderBy('id')
            ->select('id')
            ->chunk($chunkSize, function ($rows) use ($callback) {
                $callback($rows->pluck('id'));
            });
    }

    public function markAsPending(int $rowId): void
    {
        $this->markAs($rowId, ExcelRow::class, ExcelRowStatus::PENDING);
    }


    public function markAsValidated(int $rowId): void
    {
        $this->markAs($rowId, ExcelRow::class, ExcelRowStatus::VALIDATED);
    }

    public function markAsFailedValidation(int $rowId): void
    {
        $this->markAs($rowId, ExcelRow::class, ExcelRowStatus::FAILED_VALIDATION);
    }

    public function markAsProcessed(int $rowId): void
    {
        $this->markAs($rowId, ExcelRow::class, ExcelRowStatus::PROCESSED);
    }

    public function markAsFailed(int $rowId): void
    {
        $this->markAs($rowId, ExcelRow::class, ExcelRowStatus::FAILED);
    }
}
