<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Tests\Unit\DTOs;

use Akbarjimi\Purser\DTOs\ValidatedRow;

/**
 * Test the ValidatedRow DTO.
 *
 * Ensures validated rows are correctly passed to user handlers.
 *
 * @group dto
 * @group validated-row
 */
describe('ValidatedRow', function () {
    it('holds data correctly', function () {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        $row = new ValidatedRow(rowIndex: 5, data: $data);

        expect($row)
            ->rowIndex->toBe(5)
            ->data->toBe($data);
    });

    it('allows zero as row index', function () {
        $row = new ValidatedRow(rowIndex: 0, data: []);

        expect($row->rowIndex)->toBe(0);
    });

    it('accepts empty data array', function () {
        $row = new ValidatedRow(rowIndex: 1, data: []);

        expect($row->data)->toBe([]);
    });

    it('is immutable', function () {
        $data = ['field' => 'value'];
        $row = new ValidatedRow(rowIndex: 1, data: $data);

        expect($row)->toHaveProperty('rowIndex', 1);
        expect($row)->toHaveProperty('data', $data);
    });
});
