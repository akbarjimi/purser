<?php

declare(strict_types=1);

use Akbarjimi\Purser\Enums\ExcelRowStatus;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelRowError;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\ErrorReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Writer\XLSX\Writer;

uses(RefreshDatabase::class);

if (! class_exists(Writer::class)) {
    test('spreadsheet export')->skip('openspout/openspout not installed');
}

function makeFileWithErrors(int $valid = 1, int $failed = 2): array
{
    $file = ExcelFile::factory()->create();
    $sheet = ExcelSheet::factory()->for($file)->create(['sheet_index' => 0]);

    $validRows = ExcelRow::factory()->count($valid)->for($sheet)->create([
        'status' => ExcelRowStatus::VALIDATED,
    ]);

    $failedRows = ExcelRow::factory()->count($failed)->for($sheet)->create([
        'status' => ExcelRowStatus::FAILED_VALIDATION,
        'content' => ['name' => 'Broken', 'email' => 'broken@example.com', 'age' => 15],
    ]);

    foreach ($failedRows as $row) {
        ExcelRowError::factory()->for($row, 'excelRow')->create([
            'field' => 'age',
            'message' => 'The age must be at least 18.',
        ]);
    }

    return [$file, $validRows, $failedRows];
}

it('paginates only failed rows for the file', function () {
    [$file, $valid, $failed] = makeFileWithErrors(valid: 5, failed: 3);

    $page = app(ErrorReportService::class)->paginate($file->id);

    expect($page->total())->toBe(3)
        ->and($page->pluck('id')->all())->toEqualCanonicalizing($failed->pluck('id')->all());
});

it('does not leak failed rows from other files', function () {
    [$fileA] = makeFileWithErrors(valid: 0, failed: 2);
    [$fileB] = makeFileWithErrors(valid: 0, failed: 5);

    expect(app(ErrorReportService::class)->all($fileA->id))->toHaveCount(2)
        ->and(app(ErrorReportService::class)->all($fileB->id))->toHaveCount(5);
});

it('serialises failed rows to json with nested errors', function () {
    [$file] = makeFileWithErrors(valid: 0, failed: 1);

    $json = app(ErrorReportService::class)->toJson($file->id);
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toHaveCount(1)
        ->and($decoded[0])->toHaveKeys(['row_index', 'data', 'errors'])
        ->and($decoded[0]['errors'][0]['message'])->toBe('The age must be at least 18.');
});

it('writes a spreadsheet to the given disk', function () {
    Storage::fake('local');
    [$file] = makeFileWithErrors(valid: 0, failed: 2);

    $path = app(ErrorReportService::class)->toSpreadsheet($file->id, 'local');

    Storage::disk('local')->assertExists($path);
})->skip(
    ! class_exists(Writer::class),
    'openspout/openspout not installed',
);
