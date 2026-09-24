<?php

declare(strict_types=1);

use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\RowExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::disk('local')->put(
        'testing/1sheet3rows1header.xlsx',
        file_get_contents(__DIR__.'/../../stubs/1sheet3rows1header.xlsx'),
    );
});

it('does not duplicate rows when extraction runs twice on the same sheet', function () {
    $file = ExcelFile::factory()->create([
        'disk' => 'local',
        'path' => 'testing/1sheet3rows1header.xlsx',
        'status' => ExcelFileStatus::READING->value,
    ]);

    $sheet = ExcelSheet::factory()->for($file)->create([
        'name' => 'Sheet1',
        'sheet_index' => 0,
        'status' => ExcelSheetStatus::EXTRACTING->value,
    ]);

    app(RowExtractionService::class)->extract($sheet);

    $firstCount = ExcelRow::where('excel_sheet_id', $sheet->id)->count();
    expect($firstCount)->toBe(3);

    // Reset status so extraction runs again
    $sheet->update(['status' => ExcelSheetStatus::EXTRACTING->value]);
    app(RowExtractionService::class)->extract($sheet);

    $secondCount = ExcelRow::where('excel_sheet_id', $sheet->id)->count();
    expect($secondCount)->toBe(3);
});
