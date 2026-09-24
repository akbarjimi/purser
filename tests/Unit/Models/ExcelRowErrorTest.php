<?php

declare(strict_types=1);

use Akbarjimi\ExcelImporter\Models\ExcelRow;
use Akbarjimi\ExcelImporter\Models\ExcelRowError;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serialises to array with all fields', function () {
    $row = ExcelRow::factory()->create();
    $error = ExcelRowError::factory()->for($row, 'excelRow')->create([
        'field' => 'email',
        'error_type' => 'validation',
        'error_code' => 'invalid_format',
        'message' => 'Not an email.',
    ]);

    $array = $error->toArray();

    expect($array)
        ->id->toBe($error->id)
        ->excel_row_id->toBe($row->id)
        ->field->toBe('email')
        ->error_type->toBe('validation')
        ->error_code->toBe('invalid_format')
        ->message->toBe('Not an email.')
        ->created_at->toEqual($error->created_at);
});

it('belongs to an excel row', function () {
    $row = ExcelRow::factory()->create();
    $error = ExcelRowError::factory()->for($row, 'excelRow')->create();

    expect($error->excelRow)->toBeInstanceOf(ExcelRow::class)
        ->and($error->excelRow->id)->toBe($row->id);
});
