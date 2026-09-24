<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\Enums;

use Akbarjimi\Purser\Enums\ExcelChunkStatus;

/**
 * Test the chunk status state machine.
 *
 * Ensures chunks follow the correct lifecycle during processing.
 *
 * @group enums
 * @group chunk-status
 */
describe('ExcelChunkStatus', function () {
    it('allows valid transitions', function (
        ExcelChunkStatus $from,
        ExcelChunkStatus $to,
        bool $expected
    ) {
        expect($from->canTransitionTo($to))->toBe($expected);
    })->with([
        'PENDING → PROCESSING' => [ExcelChunkStatus::PENDING, ExcelChunkStatus::PROCESSING, true],
        'PENDING → FAILED' => [ExcelChunkStatus::PENDING, ExcelChunkStatus::FAILED, true],
        'PENDING → COMPLETED' => [ExcelChunkStatus::PENDING, ExcelChunkStatus::COMPLETED, false],
        'PROCESSING → COMPLETED' => [ExcelChunkStatus::PROCESSING, ExcelChunkStatus::COMPLETED, true],
        'PROCESSING → FAILED' => [ExcelChunkStatus::PROCESSING, ExcelChunkStatus::FAILED, true],
        'PROCESSING → PENDING' => [ExcelChunkStatus::PROCESSING, ExcelChunkStatus::PENDING, false],
        'COMPLETED → FAILED' => [ExcelChunkStatus::COMPLETED, ExcelChunkStatus::FAILED, false],
        'COMPLETED → PROCESSING' => [ExcelChunkStatus::COMPLETED, ExcelChunkStatus::PROCESSING, false],
        'COMPLETED → PENDING' => [ExcelChunkStatus::COMPLETED, ExcelChunkStatus::PENDING, false],
        'FAILED → COMPLETED' => [ExcelChunkStatus::FAILED, ExcelChunkStatus::COMPLETED, false],
        'FAILED → PROCESSING' => [ExcelChunkStatus::FAILED, ExcelChunkStatus::PROCESSING, false],
        'FAILED → PENDING' => [ExcelChunkStatus::FAILED, ExcelChunkStatus::PENDING, true],
    ]);

    it('has all expected status values', function () {
        $statuses = array_column(ExcelChunkStatus::cases(), 'value');
        expect($statuses)->toContain('pending', 'processing', 'completed', 'failed');
    });
});
