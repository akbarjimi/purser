<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Database\Factories;

use Akbarjimi\Purser\Enums\ExcelSheetStatus;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcelSheetFactory extends Factory
{
    protected $model = ExcelSheet::class;

    public function definition(): array
    {
        return [
            'excel_file_id' => ExcelFile::factory(),
            'name' => $this->faker->word(),
            'sheet_index' => 0,
            'total_rows' => $this->faker->numberBetween(1, 10000),
            'chunk_count' => 0,
            'processed_chunks' => 0,
            'status' => ExcelSheetStatus::PENDING->value,
            'meta' => [],
            'rows_extracted_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => ExcelSheetStatus::PENDING->value,
            'rows_extracted_at' => null,
        ]);
    }

    public function extracted(): static
    {
        return $this->state([
            'status' => ExcelSheetStatus::EXTRACTED->value,
            'rows_extracted_at' => now(),
        ]);
    }

    public function failed(?string $exception = null): static
    {
        return $this->state([
            'status' => ExcelSheetStatus::FAILED->value,
            'meta' => array_merge($this->meta ?? [], ['exception' => $exception ?? 'Unhandled exception']),
        ]);
    }

    public function withChunks(int $chunks): static
    {
        return $this->state([
            'chunk_count' => $chunks,
            'processed_chunks' => 0,
        ]);
    }
}
