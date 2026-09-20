<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Enums\ExcelChunkStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelFileStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelRowStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelSheetStatus;
use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Akbarjimi\ExcelImporter\Models\ExcelRow;
use Akbarjimi\ExcelImporter\Models\ExcelRowChunk;
use Akbarjimi\ExcelImporter\Models\ExcelSheet;
use Akbarjimi\ExcelImporter\Services\ChunkProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps row errors when a later bulk update throws', function () {
    config([
        'excel-importer-sheets.Sheet1.mapping' => ['email' => 'A'],
        'excel-importer-sheets.Sheet1.validation' => ['email' => 'required|email'],
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
        . 'WHEN NEW.id = %d '
        . "BEGIN SELECT RAISE(ABORT, 'simulated bulk failure'); END",
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