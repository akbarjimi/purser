<?php

use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Jobs\ProcessChunkJob;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\ChunkerService;
use Illuminate\Support\Facades\Bus;

it('creates deterministic chunks and dispatches jobs after commit', function () {
    Bus::fake();

    $file = ExcelFile::factory()->create();

    // Create sheets with unique indices
    $sheet1 = ExcelSheet::factory()->for($file)->create([
        'sheet_index' => 0,
        'status' => ExcelSheetStatus::EXTRACTED->value,
    ]);
    $sheet2 = ExcelSheet::factory()->for($file)->create([
        'sheet_index' => 1,
        'status' => ExcelSheetStatus::EXTRACTED->value,
    ]);

    ExcelRow::factory()->count(1001)->for($sheet1)->create();
    ExcelRow::factory()->count(1000)->for($sheet2)->create();

    $chunks = app(ChunkerService::class, ['chunkSize' => 1000])
        ->createChunksForFile($file->fresh());

    expect($chunks)->toHaveCount(3)
        ->and($chunks->pluck('size')->sort()->values()->all())->toBe([1, 1000, 1000]);

    $chunks->each(fn ($c) => ProcessChunkJob::dispatch($c->getKey())->afterCommit());

    Bus::assertDispatched(ProcessChunkJob::class, 3);
});
