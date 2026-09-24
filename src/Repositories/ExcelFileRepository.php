<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Repositories;

use Akbarjimi\Purser\Concerns\HasStatusTransitions;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Models\ExcelFile;

final class ExcelFileRepository
{
    use HasStatusTransitions;

    public function findFile(int $fileId, array $relations = []): ?ExcelFile
    {
        return ExcelFile::query()->with($relations)->find($fileId);
    }

    public function create(array $data): ExcelFile
    {
        if (! isset($data['status'])) {
            $data['status'] = ExcelFileStatus::PENDING->value;
        }

        return ExcelFile::create($data);
    }

    public function markAsPending(int $fileId): void
    {
        $this->markAs($fileId, ExcelFile::class, ExcelFileStatus::PENDING);
    }

    public function markAsReading(int $fileId): void
    {
        $this->markAs($fileId, ExcelFile::class, ExcelFileStatus::READING);
    }

    public function markAsRowsExtracting(int $fileId): void
    {
        $this->markAs($fileId, ExcelFile::class, ExcelFileStatus::ROWS_EXTRACTING);
    }

    public function markAsRowsExtracted(int $fileId): void
    {
        $this->markAs($fileId, ExcelFile::class, ExcelFileStatus::ROWS_EXTRACTED, [
            'rows_extracted_at' => now(),
        ]);
    }

    public function markAsProcessing(int $fileId): void
    {
        $this->markAs($fileId, ExcelFile::class, ExcelFileStatus::PROCESSING);
    }

    public function markAsCompleted(int $fileId): void
    {
        $this->markAs($fileId, ExcelFile::class, ExcelFileStatus::COMPLETED, [
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(int $fileId, ?string $reason = null): void
    {
        $extra = [];
        if ($reason !== null) {
            $extra['error'] = $reason;
        }
        $this->markAs($fileId, ExcelFile::class, ExcelFileStatus::FAILED, $extra);
    }

    public function getHandler(int $fileId): ?string
    {
        $file = ExcelFile::find($fileId);

        return $file ? ($file->meta['handler'] ?? null) : null;
    }

    public function recordBatchId(int $fileId, string $batchId): void
    {
        ExcelFile::whereKey($fileId)->update(['batch_id' => $batchId]);
    }
}
