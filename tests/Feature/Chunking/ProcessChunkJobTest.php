<?php

use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Jobs\ProcessChunkJob;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelRowChunk;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\ChunkProcessor;

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

    expect($chunk->fresh()->status)->toBe(\Akbarjimi\Purser\Enums\ExcelChunkStatus::COMPLETED);
});
