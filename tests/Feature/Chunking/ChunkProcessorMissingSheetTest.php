<?php

declare(strict_types=1);

use Akbarjimi\Purser\Enums\ExcelChunkStatus;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelRowChunk;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\ChunkProcessor;
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
