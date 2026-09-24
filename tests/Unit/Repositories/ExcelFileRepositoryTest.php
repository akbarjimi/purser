<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\Repositories;

use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Repositories\ExcelFileRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for the ExcelFileRepository.
 *
 * @group repositories
 * @group file-repository
 */
describe('ExcelFileRepository', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->repo = new ExcelFileRepository;
    });

    it('creates a new file record', function () {
        $data = [
            'file_name' => 'test.xlsx',
            'path' => 'imports/test.xlsx',
            'disk' => 'local',
            'size' => 1024,
            'meta' => ['handler' => 'App\\Handlers\\TestHandler'],
            'status' => ExcelFileStatus::PENDING,
        ];

        $file = $this->repo->create($data);

        expect($file)
            ->toBeInstanceOf(ExcelFile::class)
            ->file_name->toBe('test.xlsx')
            ->status->toBe(ExcelFileStatus::PENDING)
            ->meta->toBe(['handler' => 'App\\Handlers\\TestHandler']);
        $this->assertDatabaseHas('excel_files', ['id' => $file->id]);
    });

    it('finds a file by ID with optional relations', function () {
        $file = ExcelFile::factory()
            ->has(ExcelSheet::factory()->state(['sheet_index' => 0]), 'excelSheets')
            ->has(ExcelSheet::factory()->state(['sheet_index' => 1]), 'excelSheets')
            ->create();

        $found = $this->repo->findFile($file->id, ['excelSheets']);

        expect($found)->not->toBeNull();
        expect($found->relationLoaded('excelSheets'))->toBeTrue();
        expect($found->excelSheets)->toHaveCount(2);
    });

    it('returns null when file not found', function () {
        expect($this->repo->findFile(999))->toBeNull();
    });

    it('marks as reading', function () {
        $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::PENDING]);

        $this->repo->markAsReading($file->id);

        expect($file->refresh()->status)->toBe(ExcelFileStatus::READING);
    });

    it('marks as rows extracted with timestamp', function () {
        $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::READING]);

        $this->repo->markAsRowsExtracted($file->id);

        $file->refresh();
        expect($file->status)->toBe(ExcelFileStatus::ROWS_EXTRACTED);
        expect($file->rows_extracted_at)->not->toBeNull();
    });

    it('marks as failed with optional reason', function () {
        $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::READING]);

        $this->repo->markAsFailed($file->id, 'Something went wrong');

        $file->refresh();
        expect($file->status)->toBe(ExcelFileStatus::FAILED);
        expect($file->error)->toBe('Something went wrong');
    });

    it('marks as processing', function () {
        $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::ROWS_EXTRACTED]);

        $this->repo->markAsProcessing($file->id);

        expect($file->refresh()->status)->toBe(ExcelFileStatus::PROCESSING);
    });

    it('marks as completed with timestamp', function () {
        $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::PROCESSING]);

        $this->repo->markAsCompleted($file->id);

        $file->refresh();
        expect($file->status)->toBe(ExcelFileStatus::COMPLETED);
        expect($file->completed_at)->not->toBeNull();
    });

    it('gets handler from meta', function () {
        $file = ExcelFile::factory()->create([
            'meta' => ['handler' => 'App\\Handlers\\OrderHandler'],
        ]);

        expect($this->repo->getHandler($file->id))->toBe('App\\Handlers\\OrderHandler');
    });

    it('returns null when handler not set', function () {
        $file = ExcelFile::factory()->create(['meta' => []]);

        expect($this->repo->getHandler($file->id))->toBeNull();
    });

    it('records batch ID', function () {
        $file = ExcelFile::factory()->create();

        $this->repo->recordBatchId($file->id, 'batch-123');

        expect($file->refresh()->batch_id)->toBe('batch-123');
    });

    it('marks as reading from pending', function () {
        $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::PENDING]);

        $this->repo->markAsReading($file->id);

        expect($file->refresh()->status)->toBe(ExcelFileStatus::READING);
    });

    it('throws on invalid transition', function () {
        $file = ExcelFile::factory()->create(['status' => ExcelFileStatus::COMPLETED]);

        expect(fn () => $this->repo->markAsReading($file->id))
            ->toThrow(\RuntimeException::class, 'Invalid status transition');
    });
});
