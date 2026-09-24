<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\Repositories;

use Akbarjimi\Purser\DTOs\SheetInfo;
use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Exceptions\Sheet\EmptySheetException;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Repositories\ExcelSheetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for the ExcelSheetRepository.
 *
 * @group repositories
 * @group sheet-repository
 */
describe('ExcelSheetRepository', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->repo = new ExcelSheetRepository;
        $this->file = ExcelFile::factory()->create();
    });

    it('bulk creates sheets', function () {
        $sheets = [
            new SheetInfo('Users', 0, 100, 5, ['format' => 'table']),
            new SheetInfo('Orders', 1, 200, 3, ['format' => 'table']),
        ];

        $this->repo->bulkCreate($this->file->id, $sheets);

        $this->assertDatabaseCount('excel_sheets', 2);
        $this->assertDatabaseHas('excel_sheets', [
            'excel_file_id' => $this->file->id,
            'name' => 'Users',
            'sheet_index' => 0,
            'total_rows' => 100,
            'status' => ExcelSheetStatus::PENDING->value,
        ]);
        $this->assertDatabaseHas('excel_sheets', [
            'excel_file_id' => $this->file->id,
            'name' => 'Orders',
            'sheet_index' => 1,
            'total_rows' => 200,
        ]);
    });

    it('throws EmptySheetException when no sheets provided', function () {
        expect(fn () => $this->repo->bulkCreate($this->file->id, []))
            ->toThrow(EmptySheetException::class, 'No sheets discovered');
    });

    it('checks if sheets exist for a file', function () {
        expect($this->repo->existsForFile($this->file->id))->toBeFalse();

        ExcelSheet::factory()->for($this->file)->create();

        expect($this->repo->existsForFile($this->file->id))->toBeTrue();
    });

    it('gets sheets by file ID, ordered by index', function () {
        ExcelSheet::factory()->for($this->file)->create(['sheet_index' => 1]);
        ExcelSheet::factory()->for($this->file)->create(['sheet_index' => 0]);

        $sheets = $this->repo->getByFileId($this->file->id);

        expect($sheets)->toHaveCount(2);
        expect($sheets->first()->sheet_index)->toBe(0);
        expect($sheets->last()->sheet_index)->toBe(1);
    });

    it('gets a sheet by ID', function () {
        $sheet = ExcelSheet::factory()->for($this->file)->create();

        $found = $this->repo->getById($sheet->id);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($sheet->id);
    });

    it('transitions to a valid status', function () {
        $sheet = ExcelSheet::factory()->for($this->file)->create([
            'status' => ExcelSheetStatus::PENDING,
        ]);

        $this->repo->markAsExtracting($sheet->id);

        expect($sheet->refresh()->status)->toBe(ExcelSheetStatus::EXTRACTING);
    });

    it('throws exception on invalid transition', function () {
        $sheet = ExcelSheet::factory()->for($this->file)->create([
            'status' => ExcelSheetStatus::COMPLETED,
        ]);

        expect(fn () => $this->repo->markAsExtracting($sheet->id))
            ->toThrow(\RuntimeException::class, 'Invalid status transition from completed to extracting');
    });

    it('sets chunk count', function () {
        $sheet = ExcelSheet::factory()->for($this->file)->create([
            'chunk_count' => 0,
        ]);

        $this->repo->setChunkCount($sheet->id, 10);

        expect($sheet->refresh()->chunk_count)->toBe(10);
    });
});
