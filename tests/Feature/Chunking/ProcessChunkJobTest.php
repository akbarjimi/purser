<?php

use Akbarjimi\ExcelImporter\Enums\ExcelSheetStatus;
use Akbarjimi\ExcelImporter\Jobs\ProcessChunkJob;
use Akbarjimi\ExcelImporter\Models\ExcelRow;
use Akbarjimi\ExcelImporter\Models\ExcelRowChunk;
use Akbarjimi\ExcelImporter\Models\ExcelSheet;
use Akbarjimi\ExcelImporter\Services\ChunkProcessor;

it('processes a chunk idempotently', function () {
    $sheet = ExcelSheet::factory()->create([
        'status' => ExcelSheetStatus::CHUNKS_DISPATCHED->value,
        'chunk_count' => 1,
    ]);

    $rows = ExcelRow::factory()->count(5)->for($sheet)->create();

    $chunk = ExcelRowChunk::create([
        'excel_sheet_id' => $sheet->getKey(),
        'from_row_id' => $rows->first()->getKey(),
        'to_row_id' => $rows->last()->getKey(),
        'size' => 5,
        'status' => 'pending',
    ]);

    $job = new ProcessChunkJob($chunk->id);
    $job->handle(app(ChunkProcessor::class));
    $job->handle(app(ChunkProcessor::class));

    expect($chunk->fresh()->status)->toBe(\Akbarjimi\ExcelImporter\Enums\ExcelChunkStatus::COMPLETED);
});
