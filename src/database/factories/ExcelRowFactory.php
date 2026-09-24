<?php

namespace Akbarjimi\Purser\Database\Factories;

use Akbarjimi\Purser\Enums\ExcelRowStatus;
use Akbarjimi\Purser\Models\ExcelRow;
use Akbarjimi\Purser\Models\ExcelSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcelRowFactory extends Factory
{
    protected $model = ExcelRow::class;

    public function definition(): array
    {
        $content = [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'age' => $this->faker->numberBetween(18, 65),
        ];

        $encoded = json_encode($content);

        return [
            'excel_sheet_id' => ExcelSheet::factory(),
            'row_index' => $this->faker->unique()->numberBetween(1, 1000000),
            'content' => $content,
            'hash_algo' => 'sha256',
            'content_hash' => hash('sha256', $encoded),
            'status' => ExcelRowStatus::PENDING,
            'chunk_index' => $this->faker->numberBetween(0, 5),
        ];
    }

    public function withSheet(ExcelSheet $sheet): static
    {
        return $this->state(fn () => ['excel_sheet_id' => $sheet->id]);
    }
}
