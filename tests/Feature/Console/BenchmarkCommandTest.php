<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Writer\XLSX\Writer;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! class_exists(Writer::class)) {
        $this->markTestSkipped('openspout/openspout not installed');
    }

    config([
        'queue.default' => 'sync',
        'excel-importer.default_disk' => 'local',
    ]);

    Storage::fake('local');
});

it('runs the pipeline and cleans up after itself', function () {
    $this->artisan('excel:benchmark', ['--rows' => 25])
        ->expectsOutputToContain('Benchmark: 25 rows')
        ->assertSuccessful();

    expect(ExcelFile::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('excel-importer-benchmark'))->toBe([]);
});

it('keeps the fixture and file record with --keep', function () {
    $this->artisan('excel:benchmark', ['--rows' => 5, '--keep' => true])
        ->assertSuccessful();

    expect(ExcelFile::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('excel-importer-benchmark'))->toHaveCount(1);
});

it('rejects non-positive row counts', function () {
    $this->artisan('excel:benchmark', ['--rows' => 0])
        ->expectsOutputToContain('positive integer')
        ->assertFailed();
});

it('rejects non-local disks', function () {
    config(['filesystems.disks.s3' => ['driver' => 's3', 'bucket' => 'x']]);

    $this->artisan('excel:benchmark', ['--rows' => 5, '--disk' => 's3'])
        ->expectsOutputToContain('not a local disk')
        ->assertFailed();
});
