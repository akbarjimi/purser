<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Repositories;

use Akbarjimi\Purser\Concerns\HasStatusTransitions;
use Akbarjimi\Purser\Enums\ExcelChunkStatus;
use Akbarjimi\Purser\Models\ExcelRowChunk;
use Illuminate\Support\Collection;

final class ExcelRowChunkRepository
{
    use HasStatusTransitions;

    public function findOrFail(int $chunkId): ExcelRowChunk
    {
        return ExcelRowChunk::findOrFail($chunkId);
    }

    public function insertMany(array $data): Collection
    {
        if ($data === []) {
            return collect();
        }

        ExcelRowChunk::insert($data);

        $sheetId = $data[0]['excel_sheet_id'];

        $pairs = array_map(
            static fn (array $row): array => [$row['from_row_id'], $row['to_row_id']],
            $data,
        );

        return ExcelRowChunk::query()
            ->where('excel_sheet_id', $sheetId)
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as [$from, $to]) {
                    $q->orWhere(fn ($sub) => $sub->where('from_row_id', $from)->where('to_row_id', $to));
                }
            })
            ->get();
    }

    public function allChunksProcessedForSheet(int $sheetId): bool
    {
        return ExcelRowChunk::query()
            ->where('excel_sheet_id', $sheetId)
            ->where('status', '!=', ExcelChunkStatus::COMPLETED->value)
            ->doesntExist();
    }

    public function markAsPending(int $chunkId): void
    {
        $this->markAs($chunkId, ExcelRowChunk::class, ExcelChunkStatus::PENDING, [
            'error' => null,
        ]);
    }

    public function markManyAsPending(array $chunkIds): int
    {
        if ($chunkIds === []) {
            return 0;
        }

        return ExcelRowChunk::query()
            ->whereIn('id', $chunkIds)
            ->where('status', ExcelChunkStatus::FAILED->value)
            ->update([
                'status' => ExcelChunkStatus::PENDING->value,
                'error' => null,
            ]);
    }

    public function markAsProcessing(int $chunkId): void
    {
        $this->markAs($chunkId, ExcelRowChunk::class, ExcelChunkStatus::PROCESSING);
    }

    public function markAsCompleted(int $chunkId): void
    {
        $this->markAs($chunkId, ExcelRowChunk::class, ExcelChunkStatus::COMPLETED, [
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(int $chunkId, ?string $reason = null): void
    {
        $extra = $reason !== null ? ['error' => $reason] : [];
        $this->markAs($chunkId, ExcelRowChunk::class, ExcelChunkStatus::FAILED, $extra);
    }
}
