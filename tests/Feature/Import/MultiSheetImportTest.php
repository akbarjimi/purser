<?php

declare(strict_types=1);

use Akbarjimi\Purser\Contracts\ImportHandler;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\ImportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

final class MultiSheetHandler implements ImportHandler
{
    public int $count = 0;

    public function handle(int $fileId, iterable $rows): void
    {
        foreach ($rows as $_) {
            $this->count++;
        }
    }
}

beforeEach(function () {
    config([
        'queue.default' => 'sync',
        'excel-importer-sheets.Sheet2.validation' => ['A' => 'required|integer'],
        'excel-importer-sheets.Sheet3.validation' => ['A' => 'required|integer'],
        'excel-importer-sheets.Sheet2.mapping' => ['A' => 'A', 'B' => 'B'],
        'excel-importer-sheets.Sheet3.mapping' => ['A' => 'A', 'B' => 'B'],
    ]);

    Storage::disk('local')->put(
        'testing/2sheets2rows.xlsx',
        file_get_contents(__DIR__.'/../../stubs/2sheets2rows.xlsx'),
    );

    $this->handler = new MultiSheetHandler;
    app()->instance(MultiSheetHandler::class, $this->handler);
});

it('creates one sheet record per source sheet', function () {
    $file = app(ImportManager::class)
        ->import('testing/2sheets2rows.xlsx')
        ->withHandler(MultiSheetHandler::class)
        ->dispatch()
        ->refresh();

    expect($file->status)->toBe(ExcelFileStatus::COMPLETED)
        ->and(ExcelSheet::where('excel_file_id', $file->id)->count())->toBe(2);
});

it('completes every sheet', function () {
    $file = app(ImportManager::class)
        ->import('testing/2sheets2rows.xlsx')
        ->withHandler(MultiSheetHandler::class)
        ->dispatch();

    $statuses = ExcelSheet::where('excel_file_id', $file->id)
        ->pluck('status')
        ->unique()
        ->all();

    expect($statuses)->toBe([ExcelSheetStatus::COMPLETED]);
});

it('extracts four rows total across two sheets', function () {
    $file = app(ImportManager::class)
        ->import('testing/2sheets2rows.xlsx')
        ->withHandler(MultiSheetHandler::class)
        ->dispatch();

    $sheetIds = ExcelSheet::where('excel_file_id', $file->id)->pluck('id');

    expect(ExcelRow::whereIn('excel_sheet_id', $sheetIds)->count())->toBe(4);
});
