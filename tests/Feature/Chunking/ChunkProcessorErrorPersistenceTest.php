<?php

declare(strict_types=1);

use Akbarjimi\Purser\Enums\ExcelChunkStatus;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Enums\ExcelRowStatus;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelRowChunk;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\ChunkProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps row errors when a later bulk update throws', function () {
    config([
        'purser-sheets.Sheet1.mapping' => ['email' => 'A'],
        'purser-sheets.Sheet1.validation' => ['email' => 'required|email'],
    ]);

    $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::PROCESSING->value]);
    $sheet = ExcelSheet::factory()->for($file)->create([
        'name' => 'Sheet1',
        'status' => ExcelSheetStatus::CHUNKS_DISPATCHED->value,
        'chunk_count' => 1,
    ]);

    $errorRow = ExcelRow::factory()->for($sheet)->create([
        'content' => ['A' => ''],
        'status' => ExcelRowStatus::PENDING,
    ]);

    $poisonRow = ExcelRow::factory()->for($sheet)->create([
        'content' => ['A' => 'valid@example.com'],
        'status' => ExcelRowStatus::PENDING,
    ]);

    $chunk = ExcelRowChunk::create([
        'excel_sheet_id' => $sheet->id,
        'from_row_id' => $errorRow->id,
        'to_row_id' => $poisonRow->id,
        'size' => 2,
        'status' => ExcelChunkStatus::PENDING->value,
    ]);

    DB::statement(sprintf(
        'CREATE TRIGGER fail_poison_update BEFORE UPDATE ON excel_rows '
        .'WHEN NEW.id = %d '
        ."BEGIN SELECT RAISE(ABORT, 'simulated bulk failure'); END",
        $poisonRow->id,
    ));

    try {
        try {
            app(ChunkProcessor::class)->process($chunk->id);
        } catch (Throwable) {
            // Expected: the poison update aborts the chunk.
        }

        expect($errorRow->fresh()->errors()->count())->toBe(1);
    } finally {
        DB::statement('DROP TRIGGER IF EXISTS fail_poison_update');
    }
});
