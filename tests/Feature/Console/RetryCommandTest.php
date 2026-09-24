<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Enums\ExcelChunkStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelFileStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelSheetStatus;
use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Akbarjimi\ExcelImporter\Models\ExcelRowChunk;
use Akbarjimi\ExcelImporter\Models\ExcelSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('fails when the file does not exist', function () {
    $this->artisan('excel:retry', ['fileId' => 999])
        ->expectsOutputToContain('not found')
        ->assertFailed();
});

it('fails when the file is soft-deleted', function () {
    $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::FAILED->value]);
    $file->delete();

    $this->artisan('excel:retry', ['fileId' => $file->id])
        ->expectsOutputToContain('soft-deleted')
        ->assertFailed();
});

it('fails when the file is not in failed status', function () {
    $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::COMPLETED->value]);

    $this->artisan('excel:retry', ['fileId' => $file->id])
        ->expectsOutputToContain('Retry requires')
        ->assertFailed();
});

it('fails when there are no failed chunks', function () {
    $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::FAILED->value]);

    $this->artisan('excel:retry', ['fileId' => $file->id])
        ->expectsOutputToContain('no failed chunks')
        ->assertFailed();
});

it('dispatches retry batch for failed chunks', function () {
    Bus::fake();

    $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::FAILED->value]);
    $sheet = ExcelSheet::factory()->for($file)->create([
        'status' => ExcelSheetStatus::FAILED->value,
    ]);

    ExcelRowChunk::factory()
        ->count(3)
        ->sequence(fn ($sequence) => [
            'from_row_id' => $sequence->index * 10,
            'to_row_id' => $sequence->index * 10 + 9,
        ])
        ->for($sheet)
        ->create(['status' => ExcelChunkStatus::FAILED->value]);

    $this->artisan('excel:retry', ['fileId' => $file->id])->assertSuccessful();

    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 3);
});
