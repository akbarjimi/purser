<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Akbarjimi\Purser\Concerns\LogsImportActivity;
use Akbarjimi\Purser\Contracts\ChunkerInterface;
use Akbarjimi\Purser\Enums\ExcelChunkStatus;
use Akbarjimi\Purser\Enums\LogLevel;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Repositories\ExcelRowChunkRepository;
use Akbarjimi\Purser\Repositories\ExcelRowRepository;
use Akbarjimi\Purser\Repositories\ExcelSheetRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ChunkerService implements ChunkerInterface
{
    use LogsImportActivity;

    public function __construct(
        private readonly int $chunkSize,
        private readonly ExcelRowRepository $rowRepo,
        private readonly ExcelRowChunkRepository $chunkRepo,
        private readonly ExcelSheetRepository $sheetRepo,
    ) {}

    public function createChunksForFile(ExcelFile $file): Collection
    {
        return DB::transaction(function () use ($file) {
            $allChunks = collect();

            foreach ($file->excelSheets as $sheet) {
                $chunks = $this->createChunksForSheet($sheet);
                $allChunks = $allChunks->merge($chunks);
            }

            $this->importLog(LogLevel::INFO, "Created {$allChunks->count()} chunks (size {$this->chunkSize}) for file {$file->getKey()}.", [
                'chunk_count' => $allChunks->count(),
                'chunk_size' => $this->chunkSize,
                'file_id' => $file->getKey(),
            ]);

            return $allChunks;
        }, 3);
    }

    private function createChunksForSheet(ExcelSheet $sheet): Collection
    {
        $sheetId = $sheet->getKey();
        $chunkData = [];

        $this->rowRepo->chunkRowIdsBySheet(
            $sheetId,
            $this->chunkSize,
            function ($idChunk) use ($sheetId, &$chunkData) {
                $chunkData[] = [
                    'excel_sheet_id' => $sheetId,
                    'from_row_id' => $idChunk->first(),
                    'to_row_id' => $idChunk->last(),
                    'size' => $idChunk->count(),
                    'status' => ExcelChunkStatus::PENDING->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        );

        if (empty($chunkData)) {
            return collect();
        }

        $chunks = $this->chunkRepo->insertMany($chunkData);

        $this->sheetRepo->markAsChunksDispatched($sheetId);

        return $chunks;
    }
}
