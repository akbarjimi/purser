<?php

declare(strict_types=1);

use Akbarjimi\Purser\Enums\ExcelChunkStatus;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelRowChunk;
use Akbarjimi\Purser\Models\ExcelSheet;
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
