<?php

declare(strict_types=1);

use Akbarjimi\Purser\Contracts\ImportHandler;
use Akbarjimi\Purser\DTOs\ValidatedRow;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Enums\ExcelRowStatus;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Services\ImportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

final class PipelineTestHandler implements ImportHandler
{
    /** @var list<ValidatedRow> */
    public array $rows = [];

    public function handle(int $fileId, iterable $rows): void
    {
        foreach ($rows as $row) {
            $this->rows[] = $row;
        }
    }
}

beforeEach(function () {
    config(['queue.default' => 'sync']);

    $stub = __DIR__.'/../stubs/1sheet3rows1header.xlsx';
    $this->relativeTargetPath = 'testing/1sheet3rows1header.xlsx';

    Storage::disk('local')->put($this->relativeTargetPath, file_get_contents($stub));

    $this->handler = new PipelineTestHandler;
    app()->instance(PipelineTestHandler::class, $this->handler);

    $this->file = app(ImportManager::class)
        ->import($this->relativeTargetPath)
        ->withHandler(PipelineTestHandler::class)
        ->dispatch()
        ->refresh();

    $this->sheetIds = ExcelSheet::where('excel_file_id', $this->file->id)->pluck('id');
});

it('completes the file', function () {
    expect($this->file->status)->toBe(ExcelFileStatus::COMPLETED);
});

it('completes the sheet', function () {
    $sheet = ExcelSheet::where('excel_file_id', $this->file->id)->sole();

    expect($sheet->status)->toBe(ExcelSheetStatus::COMPLETED);
});

it('persists one row per source row', function () {
    expect(ExcelRow::whereIn('excel_sheet_id', $this->sheetIds)->count())->toBe(3);
});

it('validates two rows and fails the header row', function () {
    $validated = ExcelRow::whereIn('excel_sheet_id', $this->sheetIds)
        ->where('status', ExcelRowStatus::VALIDATED)
        ->count();

    $failed = ExcelRow::whereIn('excel_sheet_id', $this->sheetIds)
        ->where('status', ExcelRowStatus::FAILED_VALIDATION)
        ->count();

    expect($validated)->toBe(2)
        ->and($failed)->toBe(1);
});

it('leaves no rows pending', function () {
    $pending = ExcelRow::whereIn('excel_sheet_id', $this->sheetIds)
        ->where('status', ExcelRowStatus::PENDING)
        ->count();

    expect($pending)->toBe(0);
});

it('passes two validated rows to the handler', function () {
    expect($this->handler->rows)->toHaveCount(2);

    foreach ($this->handler->rows as $row) {
        expect($row)->toBeInstanceOf(ValidatedRow::class);
    }
});
