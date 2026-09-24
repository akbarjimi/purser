<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Enums\ExcelFileStatus;
use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Akbarjimi\ExcelImporter\Models\ExcelSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fails when the file does not exist', function () {
    $this->artisan('excel:status', ['fileId' => 999])
        ->expectsOutputToContain('not found')
        ->assertFailed();
});

it('renders a completed file', function () {
    $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::COMPLETED->value]);
    ExcelSheet::factory()->for($file)->create(['sheet_index' => 0]);

    $this->artisan('excel:status', ['fileId' => $file->id])
        ->expectsOutputToContain("File #{$file->id}")
        ->assertSuccessful();
});

it('renders a soft-deleted file without crashing', function () {
    $file = ExcelFile::factory()->create();
    $file->delete();

    $this->artisan('excel:status', ['fileId' => $file->id])->assertSuccessful();
});
