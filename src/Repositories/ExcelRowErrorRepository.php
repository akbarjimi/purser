<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Repositories;

use Akbarjimi\Purser\Models\ExcelRowError;
use Illuminate\Support\Collection;

final class ExcelRowErrorRepository
{
    /**
     * @param  array<string, array<int, string>>  $errors  Validation errors keyed by field.
     */
    public function createMany(int $rowId, array $errors): void
    {
        $now = now();

        $records = [];
        foreach ($errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $records[] = [
                    'excel_row_id' => $rowId,
                    'field' => is_string($field) ? $field : null,
                    'error_type' => 'validation',
                    'error_code' => null,
                    'message' => (string) $message,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($records !== []) {
            ExcelRowError::insert($records);
        }
    }

    public function create(int $rowId, string $type, string $message): void
    {
        ExcelRowError::create([
            'excel_row_id' => $rowId,
            'field' => null,
            'error_type' => $type,
            'error_code' => null,
            'message' => $message,
        ]);
    }

    public function getByFile(int $fileId): Collection
    {
        return ExcelRowError::query()
            ->whereHas('excelRow.excelSheet', fn ($q) => $q->where('excel_file_id', $fileId))
            ->get();
    }
}
