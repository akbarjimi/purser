<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Services\LocalFileResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

afterEach(fn () => Mockery::close());

it('returns the local disk path when the file already exists locally', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/sample.xlsx', 'content');

    $path = (new LocalFileResolver)->resolve(Storage::disk('local'), 'imports/sample.xlsx');

    expect(is_file($path))->toBeTrue();
    expect(file_get_contents($path))->toBe('content');
});

it('streams a remote file to a temp path preserving extension and contents', function () {
    $source = tempnam(sys_get_temp_dir(), 'src_').'.xlsx';
    file_put_contents($source, 'fake excel content');

    $stream = fopen($source, 'rb');

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('path')
        ->once()
        ->with('remote/file.xlsx')
        ->andReturn('/nonexistent/'.uniqid('', true).'/file.xlsx');
    $disk->shouldReceive('readStream')
        ->once()
        ->with('remote/file.xlsx')
        ->andReturn($stream);

    $path = (new LocalFileResolver)->resolve($disk, 'remote/file.xlsx');

    try {
        expect(is_file($path))->toBeTrue();
        expect(pathinfo($path, PATHINFO_EXTENSION))->toBe('xlsx');
        expect(file_get_contents($path))->toBe('fake excel content');
        expect($path)->not->toBe($source);
    } finally {
        @unlink($path);
        @unlink($source);
    }
});
