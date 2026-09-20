<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Enums\ExcelFileStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelSheetStatus;
use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Akbarjimi\ExcelImporter\Models\ExcelRow;
use Akbarjimi\ExcelImporter\Models\ExcelSheet;
use Akbarjimi\ExcelImporter\Services\RowExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

uses(RefreshDatabase::class);

it('preserves duplicate content rows as distinct excel_rows', function () {
    $relativePath = 'testing/duplicate-rows.xlsx';
    $absolutePath = Storage::disk('local')->path($relativePath);
    @mkdir(dirname($absolutePath), 0755, true);

    $writer = new Writer;
    $writer->openToFile($absolutePath);
    $writer->addRow(Row::fromValues(['name', 'email', 'age']));
    $writer->addRow(Row::fromValues(['Same Person', 'same@example.com', 30]));
    $writer->addRow(Row::fromValues(['Same Person', 'same@example.com', 30]));
    $writer->close();

    $file = ExcelFile::factory()->create([
        'disk' => 'local',
        'path' => $relativePath,
        'status' => ExcelFileStatus::READING->value,
    ]);

    $sheet = ExcelSheet::factory()->for($file)->create([
        'name' => 'Sheet1',
        'sheet_index' => 0,
        'status' => ExcelSheetStatus::EXTRACTING->value,
    ]);

    app(RowExtractionService::class)->extract($sheet);

    $count = ExcelRow::where('excel_sheet_id', $sheet->id)->count();
    expect($count)->toBe(3);

    $sheet->update(['status' => ExcelSheetStatus::EXTRACTING->value]);
    app(RowExtractionService::class)->extract($sheet);

    expect(ExcelRow::where('excel_sheet_id', $sheet->id)->count())->toBe(3);
});
