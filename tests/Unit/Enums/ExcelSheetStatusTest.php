<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\Enums;

use Akbarjimi\Purser\Enums\ExcelSheetStatus;

/**
 * Test the sheet status state machine.
 *
 * Ensures sheets follow the correct lifecycle during extraction
 * and chunk processing.
 *
 * @group enums
 * @group sheet-status
 */
describe('ExcelSheetStatus', function () {
    it('allows valid transitions', function (
        ExcelSheetStatus $from,
        ExcelSheetStatus $to,
        bool $expected
    ) {
        expect($from->canTransitionTo($to))->toBe($expected);
    })->with([
        'PENDING → EXTRACTING' => [ExcelSheetStatus::PENDING, ExcelSheetStatus::EXTRACTING, true],
        'PENDING → FAILED' => [ExcelSheetStatus::PENDING, ExcelSheetStatus::FAILED, true],
        'PENDING → EXTRACTED' => [ExcelSheetStatus::PENDING, ExcelSheetStatus::EXTRACTED, false],
        'PENDING → CHUNKS_DISPATCHED' => [ExcelSheetStatus::PENDING, ExcelSheetStatus::CHUNKS_DISPATCHED, false],
        'PENDING → COMPLETED' => [ExcelSheetStatus::PENDING, ExcelSheetStatus::COMPLETED, false],
        'EXTRACTING → EXTRACTED' => [ExcelSheetStatus::EXTRACTING, ExcelSheetStatus::EXTRACTED, true],
        'EXTRACTING → FAILED' => [ExcelSheetStatus::EXTRACTING, ExcelSheetStatus::FAILED, true],
        'EXTRACTING → CHUNKS_DISPATCHED' => [ExcelSheetStatus::EXTRACTING, ExcelSheetStatus::CHUNKS_DISPATCHED, false],
        'EXTRACTING → COMPLETED' => [ExcelSheetStatus::EXTRACTING, ExcelSheetStatus::COMPLETED, false],
        'EXTRACTED → CHUNKS_DISPATCHED' => [ExcelSheetStatus::EXTRACTED, ExcelSheetStatus::CHUNKS_DISPATCHED, true],
        'EXTRACTED → FAILED' => [ExcelSheetStatus::EXTRACTED, ExcelSheetStatus::FAILED, true],
        'EXTRACTED → COMPLETED' => [ExcelSheetStatus::EXTRACTED, ExcelSheetStatus::COMPLETED, false],
        'EXTRACTED → EXTRACTING' => [ExcelSheetStatus::EXTRACTED, ExcelSheetStatus::EXTRACTING, true],
        'CHUNKS_DISPATCHED → COMPLETED' => [ExcelSheetStatus::CHUNKS_DISPATCHED, ExcelSheetStatus::COMPLETED, true],
        'CHUNKS_DISPATCHED → FAILED' => [ExcelSheetStatus::CHUNKS_DISPATCHED, ExcelSheetStatus::FAILED, true],
        'CHUNKS_DISPATCHED → EXTRACTED' => [ExcelSheetStatus::CHUNKS_DISPATCHED, ExcelSheetStatus::EXTRACTED, false],
        'COMPLETED → FAILED' => [ExcelSheetStatus::COMPLETED, ExcelSheetStatus::FAILED, false],
        'COMPLETED → CHUNKS_DISPATCHED' => [ExcelSheetStatus::COMPLETED, ExcelSheetStatus::CHUNKS_DISPATCHED, false],
        'FAILED → COMPLETED' => [ExcelSheetStatus::FAILED, ExcelSheetStatus::COMPLETED, false],
        'FAILED → EXTRACTING' => [ExcelSheetStatus::FAILED, ExcelSheetStatus::EXTRACTING, false],
    ]);

    it('has all expected status values', function () {
        $statuses = array_column(ExcelSheetStatus::cases(), 'value');
        expect($statuses)->toContain('pending', 'extracting', 'extracted', 'chunks_dispatched', 'completed', 'failed');
    });
});
