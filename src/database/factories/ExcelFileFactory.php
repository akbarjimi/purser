<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Database\Factories;

use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Models\ExcelFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcelFileFactory extends Factory
{
    protected $model = ExcelFile::class;

    public function definition(): array
    {
        return [
            'file_name' => $this->faker->word().'.xlsx',
            'path' => 'testing/'.$this->faker->uuid().'.xlsx',
            'disk' => 'local',
            'size' => $this->faker->numberBetween(1024, 10485760),
            'status' => ExcelFileStatus::PENDING,
            'meta' => [],
            'completed_at' => null,
            'error' => null,
            'batch_id' => null,
        ];
    }
}
