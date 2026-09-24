<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Enums\ExcelChunkStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelFileStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelRowStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelSheetStatus;
use Akbarjimi\ExcelImporter\Jobs\ProcessChunkJob;
use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Akbarjimi\ExcelImporter\Models\ExcelRow;
use Akbarjimi\ExcelImporter\Models\ExcelRowChunk;
use Akbarjimi\ExcelImporter\Models\ExcelSheet;
use Akbarjimi\ExcelImporter\Services\ChunkProcessor;
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
