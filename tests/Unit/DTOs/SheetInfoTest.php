<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\DTOs;

use Akbarjimi\Purser\DTOs\SheetInfo;

/**
 * Test the SheetInfo DTO.
 *
 * Ensures sheet metadata is correctly encapsulated and
 * can be created from PhpSpreadsheet data.
 *
 * @group dto
 * @group sheet-info
 */
describe('SheetInfo', function () {
    it('creates from PhpSpreadsheet data', function () {
        $info = [
            'worksheetName' => 'Users',
            'totalRows' => 100,
            'totalColumns' => 5,
        ];

        $sheetInfo = SheetInfo::fromPhpSpreadsheet($info, 0);

        expect($sheetInfo)
            ->toBeInstanceOf(SheetInfo::class)
            ->name->toBe('Users')
            ->index->toBe(0)
            ->totalRows->toBe(100)
            ->totalColumns->toBe(5)
            ->raw->toBe($info);
    });

    it('uses default name when worksheetName missing', function () {
        $info = ['totalRows' => 10];

        $sheetInfo = SheetInfo::fromPhpSpreadsheet($info, 1);

        expect($sheetInfo)->name->toBe('Sheet1');
    });

    it('casts totalRows and totalColumns to integers', function () {
        $info = [
            'worksheetName' => 'Sheet',
            'totalRows' => '100',
            'totalColumns' => '5',
        ];

        $sheetInfo = SheetInfo::fromPhpSpreadsheet($info, 0);

        expect($sheetInfo->totalRows)->toBeInt()->toBe(100);
        expect($sheetInfo->totalColumns)->toBeInt()->toBe(5);
    });

    it('handles empty raw data', function () {
        $sheetInfo = new SheetInfo('Sheet', 0, 0, 0, []);

        expect($sheetInfo->raw)->toBe([]);
    });

    it('is immutable', function () {
        $sheetInfo = new SheetInfo('Sheet', 0, 100, 5, ['foo' => 'bar']);

        // All properties should be readonly
        expect($sheetInfo)->toHaveProperty('name', 'Sheet');
        expect($sheetInfo)->toHaveProperty('index', 0);
        expect($sheetInfo)->toHaveProperty('totalRows', 100);
        expect($sheetInfo)->toHaveProperty('totalColumns', 5);
    });
});
