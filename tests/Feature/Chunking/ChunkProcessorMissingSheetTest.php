<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Enums\ExcelChunkStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelFileStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelSheetStatus;
use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Akbarjimi\ExcelImporter\Models\ExcelRow;
use Akbarjimi\ExcelImporter\Models\ExcelRowChunk;
use Akbarjimi\ExcelImporter\Models\ExcelSheet;
use Akbarjimi\ExcelImporter\Services\ChunkProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('marks the chunk failed when its sheet no longer exists', function () {
    $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::PROCESSING->value]);
    $sheet = ExcelSheet::factory()->for($file)->create([
        'status' => ExcelSheetStatus::CHUNKS_DISPATCHED->value,
    ]);

    $rows = ExcelRow::factory()->count(2)->for($sheet)->create();

    $chunk = ExcelRowChunk::create([
        'excel_sheet_id' => $sheet->id,
        'from_row_id' => $rows->first()->id,
        'to_row_id' => $rows->last()->id,
        'size' => 2,
        'status' => ExcelChunkStatus::PENDING->value,
    ]);

    DB::statement('PRAGMA foreign_keys = OFF');

    try {
        DB::table('excel_sheets')->where('id', $sheet->id)->delete();
    } finally {
        DB::statement('PRAGMA foreign_keys = ON');
    }

    app(ChunkProcessor::class)->process($chunk->id);

    expect($chunk->fresh()->status)->toBe(ExcelChunkStatus::FAILED)
        ->and($chunk->fresh()->error)->toBe('Sheet not found.');
});