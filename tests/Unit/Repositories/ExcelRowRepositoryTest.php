<?php

declare(strict_types=1);

namespace Akbarjimi\ExcelImporter\Tests\Unit\Repositories;

use Akbarjimi\ExcelImporter\DTOs\ValidatedRow;
use Akbarjimi\ExcelImporter\Enums\ExcelRowStatus;
use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Akbarjimi\ExcelImporter\Models\ExcelRow;
use Akbarjimi\ExcelImporter\Models\ExcelSheet;
use Akbarjimi\ExcelImporter\Repositories\ExcelRowRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tests for the ExcelRowRepository.
 *
 * @group repositories
 * @group row-repository
 */
describe('ExcelRowRepository', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->repo = new ExcelRowRepository;
        $this->file = ExcelFile::factory()->create();
        $this->sheet = ExcelSheet::factory()->for($this->file)->create();
    });

    it('bulk inserts rows', function () {
        $now = now();
        $rows = [
            [
                'excel_sheet_id' => $this->sheet->id,
                'content' => json_encode(['name' => 'John']),
                'hash_algo' => 'sha256',
                'content_hash' => hash('sha256', json_encode(['name' => 'John'])),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'excel_sheet_id' => $this->sheet->id,
                'content' => json_encode(['name' => 'Jane']),
                'hash_algo' => 'sha256',
                'content_hash' => hash('sha256', json_encode(['name' => 'Jane'])),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $this->repo->bulkUpsert($rows);

        $this->assertDatabaseCount('excel_rows', 2);
        $this->assertDatabaseHas('excel_rows', [
            'excel_sheet_id' => $this->sheet->id,
            'content' => json_encode(['name' => 'John']),
        ]);
    });

    it('does nothing on empty bulk insert', function () {
        $this->repo->bulkUpsert([]);
        $this->assertDatabaseCount('excel_rows', 0);
    });

    it('bulk upserts rows (updates existing, inserts new)', function () {
        $existing = ExcelRow::factory()->for($this->sheet)->create([
            'content' => json_encode(['name' => 'John']),
            'content_hash' => hash('sha256', json_encode(['name' => 'John'])),
            'hash_algo' => 'sha256',
            'status' => ExcelRowStatus::PENDING,
            'row_index' => 1,
        ]);

        $rows = [
            [
                'id' => $existing->id,
                'excel_sheet_id' => $this->sheet->id,
                'content' => json_encode(['name' => 'John Updated']),
                'content_hash' => $existing->content_hash,
                'hash_algo' => $existing->hash_algo,
                'status' => ExcelRowStatus::VALIDATED,
                'row_index' => 1,
                'updated_at' => now(),
            ],
            [
                'excel_sheet_id' => $this->sheet->id,
                'content' => json_encode(['name' => 'New']),
                'content_hash' => hash('sha256', json_encode(['name' => 'New'])),
                'hash_algo' => 'sha256',
                'status' => ExcelRowStatus::PENDING,
                'row_index' => 2,
                'updated_at' => now(),
            ],
        ];

        $this->repo->bulkUpsert($rows);

        $this->assertDatabaseCount('excel_rows', 2);
        $this->assertDatabaseHas('excel_rows', [
            'id' => $existing->id,
            'content' => json_encode(['name' => 'John Updated']),
            'status' => ExcelRowStatus::VALIDATED->value,
            'content_hash' => $existing->content_hash,
        ]);
        $this->assertDatabaseHas('excel_rows', [
            'content' => json_encode(['name' => 'New']),
            'status' => ExcelRowStatus::PENDING->value,
        ]);
    });

    it('gets validated rows for a file as LazyCollection of ValidatedRow', function () {
        // Create some validated rows.
        ExcelRow::factory()->for($this->sheet)->count(3)->create([
            'status' => ExcelRowStatus::VALIDATED,
            'row_index' => 5,
        ]);
        // Create some non-validated rows that should be ignored.
        ExcelRow::factory()->for($this->sheet)->count(2)->create([
            'status' => ExcelRowStatus::PENDING,
        ]);

        $rows = $this->repo->getValidatedRowsForFile($this->file->id);

        expect($rows)->toHaveCount(3);
        foreach ($rows as $row) {
            expect($row)->toBeInstanceOf(ValidatedRow::class);
            expect($row->rowIndex)->toBe(5);
        }
    });

    it('throws on invalid row transition', function () {
        $row = ExcelRow::factory()->for($this->sheet)->create([
            'status' => ExcelRowStatus::PENDING,
        ]);

        expect(fn () => $this->repo->markAsProcessed($row->id))
            ->toThrow(\RuntimeException::class, 'Invalid status transition from pending to processed');
    });
});
