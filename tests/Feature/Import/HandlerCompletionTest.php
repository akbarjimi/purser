<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Contracts\ImportHandler;
use Akbarjimi\ExcelImporter\Enums\ExcelFileStatus;
use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Akbarjimi\ExcelImporter\Services\ImportManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

final class ThrowingHandler implements ImportHandler
{
    public function handle(int $fileId, iterable $rows): void
    {
        throw new \RuntimeException('handler exploded mid-write');
    }
}

final class CountingHandler implements ImportHandler
{
    public int $calls = 0;

    public function handle(int $fileId, iterable $rows): void
    {
        $this->calls++;
        foreach ($rows as $row) {
            // consume stream
        }
    }
}

beforeEach(function () {
    config(['queue.default' => 'sync']);

    $stub = __DIR__.'/../../stubs/1sheet3rows1header.xlsx';
    $this->relativeTargetPath = 'testing/1sheet3rows1header.xlsx';
    Storage::disk('local')->put($this->relativeTargetPath, file_get_contents($stub));
});

it('marks the file failed when the handler throws, not completed', function () {
    app()->instance(ThrowingHandler::class, new ThrowingHandler);

    try {
        app(ImportManager::class)
            ->import($this->relativeTargetPath)
            ->withHandler(ThrowingHandler::class)
            ->dispatch();
    } catch (\Throwable) {
        // Sync queue rethrows after InvokeImportHandler::failed().
    }

    $file = ExcelFile::query()->latest('id')->first();

    expect($file)->not->toBeNull()
        ->and($file->status)->toBe(ExcelFileStatus::FAILED)
        ->and($file->error)->toContain('handler exploded');
});

it('marks the file completed only after the handler succeeds', function () {
    $handler = new CountingHandler;
    app()->instance(CountingHandler::class, $handler);

    $file = app(ImportManager::class)
        ->import($this->relativeTargetPath)
        ->withHandler(CountingHandler::class)
        ->dispatch()
        ->refresh();

    expect($file->status)->toBe(ExcelFileStatus::COMPLETED)
        ->and($handler->calls)->toBe(1);
});

it('lets excel:retry re-run the handler after a handler-only failure', function () {
    app()->instance(ThrowingHandler::class, new ThrowingHandler);

    try {
        app(ImportManager::class)
            ->import($this->relativeTargetPath)
            ->withHandler(ThrowingHandler::class)
            ->dispatch();
    } catch (\Throwable) {
    }

    $file = ExcelFile::query()->latest('id')->first();
    expect($file->status)->toBe(ExcelFileStatus::FAILED);

    $handler = new CountingHandler;
    app()->instance(CountingHandler::class, $handler);
    $file->update(['meta' => array_merge($file->meta ?? [], ['handler' => CountingHandler::class])]);

    $this->artisan('excel:retry', ['fileId' => $file->id])->assertSuccessful();

    expect($file->fresh()->status)->toBe(ExcelFileStatus::COMPLETED)
        ->and($handler->calls)->toBe(1);
});
