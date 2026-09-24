<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Console\Commands;

use Akbarjimi\Purser\Enums\ExcelChunkStatus;
use Akbarjimi\Purser\Enums\ExcelRowStatus;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelRowChunk;
use Akbarjimi\Purser\Models\ExcelRowError;
use Illuminate\Console\Command;

final class StatusCommand extends Command
{
    protected $signature = 'excel:status {fileId : Excel file ID}';

    protected $description = 'Display status, progress, and error counts for an Excel import file.';

    public function handle(): int
    {
        $fileId = (int) $this->argument('fileId');
        $file = ExcelFile::with('excelSheets')->withTrashed()->find($fileId);

        if ($file === null) {
            $this->error("File [{$fileId}] not found.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("File #{$file->id}");
        $this->table(
            ['Field', 'Value'],
            [
                ['Name', $file->file_name],
                ['Status', $file->status->value],
                ['Batch ID', $file->batch_id ?? '—'],
                ['Error', $file->error ?? '—'],
                ['Trashed', $file->trashed() ? 'yes' : 'no'],
                ['Created', $file->created_at?->toDateTimeString() ?? '—'],
                ['Rows Extracted', $file->rows_extracted_at?->toDateTimeString() ?? '—'],
                ['Completed', $file->completed_at?->toDateTimeString() ?? '—'],
            ],
        );

        $this->newLine();
        $this->info('Sheets');
        $sheetRows = $file->excelSheets->map(fn ($s): array => [
            $s->id,
            $s->name,
            $s->sheet_index,
            $s->status->value,
            $s->total_rows,
            $s->chunk_count,
            $s->processed_chunks,
        ])->all();

        $sheetRows === []
            ? $this->line('  (none)')
            : $this->table(['ID', 'Name', 'Idx', 'Status', 'Rows', 'Chunks', 'Done'], $sheetRows);

        $this->newLine();
        $this->info('Chunks');
        $chunkCounts = ExcelRowChunk::query()
            ->whereHas('excelSheet', fn ($q) => $q->where('excel_file_id', $fileId)->withTrashed())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $this->renderCounts(ExcelChunkStatus::cases(), $chunkCounts);

        $this->newLine();
        $this->info('Rows');
        $rowCounts = ExcelRow::query()
            ->withTrashed()
            ->whereHas('excelSheet', fn ($q) => $q->where('excel_file_id', $fileId)->withTrashed())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $this->renderCounts(ExcelRowStatus::cases(), $rowCounts);

        $this->newLine();
        $errorCount = ExcelRowError::query()
            ->whereHas('excelRow.excelSheet', fn ($q) => $q->where('excel_file_id', $fileId)->withTrashed())
            ->count();

        $this->info("Errors: {$errorCount}");

        return self::SUCCESS;
    }

    /** @param array<int, \BackedEnum> $cases */
    private function renderCounts(array $cases, array $counts): void
    {
        $rows = [];
        foreach ($cases as $case) {
            $rows[] = [$case->value, $counts[$case->value] ?? 0];
        }
        $this->table(['Status', 'Count'], $rows);
    }
}
