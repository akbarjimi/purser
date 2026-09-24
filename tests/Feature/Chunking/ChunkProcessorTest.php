<?php

declare(strict_types=1);

use Akbarjimi\Purser\Contracts\TransformerInterface;
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

uses(RefreshDatabase::class);

final class ThrowingTransformer implements TransformerInterface
{
    public function transform(array $mappedRow, ExcelSheet $sheet): array
    {
        throw new RuntimeException('Transformer exploded');
    }
}

/**
 * @return array{0: ExcelFile, 1: ExcelSheet, 2: \Illuminate\Support\Collection<int, ExcelRow>, 3: ExcelRowChunk}
 */
function makeSheetWithChunk(int $rowCount = 3, array $content = ['A' => 'value']): array
{
    $file = ExcelFile::factory()->create([
        'status' => ExcelFileStatus::PROCESSING->value,
    ]);

    $sheet = ExcelSheet::factory()->for($file)->create([
        'name' => 'Sheet1',
        'status' => ExcelSheetStatus::CHUNKS_DISPATCHED->value,
        'chunk_count' => 1,
    ]);

    $rows = ExcelRow::factory()->count($rowCount)->for($sheet)->create([
        'content' => $content,
        'status' => ExcelRowStatus::PENDING,
    ]);

    $chunk = ExcelRowChunk::create([
        'excel_sheet_id' => $sheet->id,
        'from_row_id' => $rows->first()->id,
        'to_row_id' => $rows->last()->id,
        'size' => $rowCount,
        'status' => ExcelChunkStatus::PENDING->value,
    ]);

    return [$file, $sheet, $rows, $chunk];
}

it('marks row as failed when transformer throws, chunk still completes', function () {
    config([
        'excel-importer-sheets.Sheet1.mapping' => ['email' => 'A'],
        'excel-importer-sheets.Sheet1.transformer' => ThrowingTransformer::class,
    ]);

    [$file, $sheet, $rows, $chunk] = makeSheetWithChunk();

    app(ChunkProcessor::class)->process($chunk->id);

    expect($chunk->fresh()->status)->toBe(ExcelChunkStatus::COMPLETED)
        ->and($rows->fresh()->pluck('status')->unique()->all())
        ->toBe([ExcelRowStatus::FAILED])
        ->and($rows->first()->fresh()->errors()->count())->toBe(1);
});

it('marks row as failed_validation when validation fails', function () {
    config([
        'excel-importer-sheets.Sheet1.mapping' => ['email' => 'A'],
        'excel-importer-sheets.Sheet1.transformer' => null,
        'excel-importer-sheets.Sheet1.validation' => ['email' => 'required|email'],
    ]);

    [$file, $sheet, $rows, $chunk] = makeSheetWithChunk(2);

    $rows->first()->update(['content' => ['A' => 'not-an-email']]);
    $rows->last()->update(['content' => ['A' => 'ok@example.com']]);

    app(ChunkProcessor::class)->process($chunk->id);

    $statuses = ExcelRow::whereIn('id', $rows->pluck('id'))
        ->pluck('status', 'id')
        ->map(fn ($s) => $s->value)
        ->all();

    expect($statuses[$rows->first()->id])->toBe(ExcelRowStatus::FAILED_VALIDATION->value)
        ->and($statuses[$rows->last()->id])->toBe(ExcelRowStatus::VALIDATED->value);
});

it('is idempotent when chunk already completed', function () {
    [$file, $sheet, $rows, $chunk] = makeSheetWithChunk(1);

    $chunk->update(['status' => ExcelChunkStatus::COMPLETED->value]);

    app(ChunkProcessor::class)->process($chunk->id);

    expect($chunk->fresh()->status)->toBe(ExcelChunkStatus::COMPLETED)
        ->and($rows->first()->fresh()->status)->toBe(ExcelRowStatus::PENDING);
});

it('fails chunk when file is soft-deleted', function () {
    [$file, $sheet, $rows, $chunk] = makeSheetWithChunk(1);

    $file->delete();

    app(ChunkProcessor::class)->process($chunk->id);

    expect($chunk->fresh()->status)->toBe(ExcelChunkStatus::FAILED)
        ->and($chunk->fresh()->error)->toBe('File deleted.');
});

it('completes the sheet when all chunks are done', function () {
    [$file, $sheet, $rows, $chunk] = makeSheetWithChunk(1);

    app(ChunkProcessor::class)->process($chunk->id);

    expect($sheet->fresh()->status)->toBe(ExcelSheetStatus::COMPLETED);
});
