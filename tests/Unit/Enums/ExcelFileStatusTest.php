<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\Enums;

use Akbarjimi\Purser\Enums\ExcelFileStatus;

/**
 * Test the file status state machine.
 *
 * Ensures that files can only transition between valid states,
 * preventing corruption of the import pipeline.
 *
 * @group enums
 * @group file-status
 */
describe('ExcelFileStatus', function () {
    it('allows valid transitions', function (
        ExcelFileStatus $from,
        ExcelFileStatus $to,
        bool $expected
    ) {
        expect($from->canTransitionTo($to))->toBe($expected);
    })->with([
        'PENDING → READING' => [ExcelFileStatus::PENDING, ExcelFileStatus::READING, true],
        'PENDING → FAILED' => [ExcelFileStatus::PENDING, ExcelFileStatus::FAILED, true],
        'PENDING → PROCESSING' => [ExcelFileStatus::PENDING, ExcelFileStatus::PROCESSING, false],
        'PENDING → COMPLETED' => [ExcelFileStatus::PENDING, ExcelFileStatus::COMPLETED, false],
        'PENDING → ROWS_EXTRACTED' => [ExcelFileStatus::PENDING, ExcelFileStatus::ROWS_EXTRACTED, false],
        'READING → ROWS_EXTRACTED' => [ExcelFileStatus::READING, ExcelFileStatus::ROWS_EXTRACTED, true],
        'READING → FAILED' => [ExcelFileStatus::READING, ExcelFileStatus::FAILED, true],
        'READING → PROCESSING' => [ExcelFileStatus::READING, ExcelFileStatus::PROCESSING, false],
        'READING → COMPLETED' => [ExcelFileStatus::READING, ExcelFileStatus::COMPLETED, false],
        'ROWS_EXTRACTED → PROCESSING' => [ExcelFileStatus::ROWS_EXTRACTED, ExcelFileStatus::PROCESSING, true],
        'ROWS_EXTRACTED → FAILED' => [ExcelFileStatus::ROWS_EXTRACTED, ExcelFileStatus::FAILED, true],
        'ROWS_EXTRACTED → COMPLETED' => [ExcelFileStatus::ROWS_EXTRACTED, ExcelFileStatus::COMPLETED, false],
        'ROWS_EXTRACTED → READING' => [ExcelFileStatus::ROWS_EXTRACTED, ExcelFileStatus::READING, false],
        'PROCESSING → COMPLETED' => [ExcelFileStatus::PROCESSING, ExcelFileStatus::COMPLETED, true],
        'PROCESSING → FAILED' => [ExcelFileStatus::PROCESSING, ExcelFileStatus::FAILED, true],
        'PROCESSING → ROWS_EXTRACTED' => [ExcelFileStatus::PROCESSING, ExcelFileStatus::ROWS_EXTRACTED, false],
        'COMPLETED → FAILED' => [ExcelFileStatus::COMPLETED, ExcelFileStatus::FAILED, false],
        'COMPLETED → PROCESSING' => [ExcelFileStatus::COMPLETED, ExcelFileStatus::PROCESSING, false],
        'FAILED → COMPLETED' => [ExcelFileStatus::FAILED, ExcelFileStatus::COMPLETED, false],
        'FAILED → PROCESSING' => [ExcelFileStatus::FAILED, ExcelFileStatus::PROCESSING, true],
    ]);

    it('has all expected status values', function () {
        $statuses = array_column(ExcelFileStatus::cases(), 'value');
        expect($statuses)->toContain('pending', 'reading', 'rows_extracted', 'processing', 'completed', 'failed');
    });

    it('is terminal when completed', function () {
        expect(ExcelFileStatus::COMPLETED->canTransitionTo(ExcelFileStatus::FAILED))->toBeFalse();
        expect(ExcelFileStatus::COMPLETED->canTransitionTo(ExcelFileStatus::PROCESSING))->toBeFalse();
        expect(ExcelFileStatus::COMPLETED->canTransitionTo(ExcelFileStatus::READING))->toBeFalse();
    });

    it('only allows retry from failed', function () {
        expect(ExcelFileStatus::FAILED->canTransitionTo(ExcelFileStatus::PROCESSING))->toBeTrue()
            ->and(ExcelFileStatus::FAILED->canTransitionTo(ExcelFileStatus::COMPLETED))->toBeFalse()
            ->and(ExcelFileStatus::FAILED->canTransitionTo(ExcelFileStatus::READING))->toBeFalse();
    });
});
