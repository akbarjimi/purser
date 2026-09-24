<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Database\Factories;

use Akbarjimi\Purser\Enums\ExcelChunkStatus;
use Akbarjimi\Purser\Models\ExcelRowChunk;
use Akbarjimi\Purser\Models\ExcelSheet;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcelRowChunkFactory extends Factory
{
    protected $model = ExcelRowChunk::class;

    public function definition(): array
    {
        return [
            'excel_sheet_id' => ExcelSheet::factory(),
            'from_row_id' => 1,
            'to_row_id' => 3,
            'size' => 3,
            'status' => ExcelChunkStatus::PENDING->value,
            'attempts' => 0,
            'error' => null,
            'dispatched_at' => null,
            'processed_at' => null,
        ];
    }
}
