<?php

namespace Akbarjimi\Purser\Database\Factories;

use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelRowError;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcelRowErrorFactory extends Factory
{
    protected $model = ExcelRowError::class;

    public function definition(): array
    {
        return [
            'excel_row_id' => ExcelRow::factory(),

            'field' => $this->faker->randomElement(['A1', 'B2', 'C3', null]),

            'error_type' => $this->faker->randomElement(['validation', 'transform', 'system']),

            'error_code' => $this->faker->optional()->randomElement([
                'required_field_missing',
                'invalid_format',
                'type_mismatch',
                'system_exception',
            ]),

            'message' => $this->faker->sentence(12),
        ];
    }

    public function forRow(ExcelRow $row): static
    {
        return $this->state(fn () => [
            'excel_row_id' => $row->id,
        ]);
    }

    public function validationError(string $field = 'A1'): static
    {
        return $this->state(fn () => [
            'field' => $field,
            'error_type' => 'validation',
            'error_code' => 'required_field_missing',
            'message' => 'This field is required.',
        ]);
    }

    public function systemError(): static
    {
        return $this->state(fn () => [
            'field' => null,
            'error_type' => 'system',
            'error_code' => 'system_exception',
            'message' => 'Unexpected exception occurred.',
        ]);
    }
}
