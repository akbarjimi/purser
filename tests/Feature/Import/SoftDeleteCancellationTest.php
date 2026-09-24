<?php

declare(strict_types=1);

use Akbarjimi\Purser\Enums\ExcelChunkStatus;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Enums\ExcelRowStatus;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Jobs\ProcessChunkJob;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelRowChunk;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\ChunkProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuses to process a chunk whose file was soft-deleted', function () {
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

    $file->delete();

    (new ProcessChunkJob($chunk->id))->handle(app(ChunkProcessor::class));

    expect($chunk->fresh()->status)->toBe(ExcelChunkStatus::FAILED)
        ->and($chunk->fresh()->error)->toBe('File deleted.')
        ->and($rows->fresh()->pluck('status')->unique()->all())
        ->toBe([ExcelRowStatus::PENDING]);
});
