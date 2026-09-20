<?php

declare(strict_types=1);

namespace Akbarjimi\ExcelImporter\Services;

use Akbarjimi\ExcelImporter\Enums\ExcelChunkStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelRowStatus;
use Akbarjimi\ExcelImporter\Models\ExcelRow;
use Akbarjimi\ExcelImporter\Repositories\ExcelRowChunkRepository;
use Akbarjimi\ExcelImporter\Repositories\ExcelRowErrorRepository;
use Akbarjimi\ExcelImporter\Repositories\ExcelRowRepository;
use Akbarjimi\ExcelImporter\Repositories\ExcelSheetRepository;
use Throwable;

final class ChunkProcessor
{
    public function __construct(
        private readonly ExcelRowRepository $rowRepository,
        private readonly ExcelRowChunkRepository $rowChunkRepository,
        private readonly ExcelRowErrorRepository $rowErrorRepository,
        private readonly ExcelSheetRepository $sheetRepository,
        private readonly TransformService $transformer,
        private readonly ValidateService $validator,
        private readonly int $batchSize,
    ) {}

    public function process(int $chunkId): void
    {
        $chunk = $this->rowChunkRepository->findOrFail($chunkId);

        if ($chunk->status === ExcelChunkStatus::COMPLETED) {
            return;
        }

        $sheet = $this->sheetRepository->getById($chunk->excel_sheet_id);

        if ($sheet === null) {
            $this->rowChunkRepository->markAsFailed($chunkId, 'Sheet not found.');

            return;
        }

        $excelFile = $sheet->excelFile;

        if ($excelFile === null || $excelFile->trashed()) {
            $this->rowChunkRepository->markAsFailed($chunkId, 'File deleted.');

            return;
        }

        $this->rowChunkRepository->markAsProcessing($chunkId);

        $rows = $this->rowRepository->getRowsBetween(
            $chunk->excel_sheet_id,
            $chunk->from_row_id,
            $chunk->to_row_id,
        );

        $buffer = [];

        try {
            foreach ($rows as $row) {
                try {
                    $transformed = $this->transformer->apply($row->content, $sheet);
                    $errors = $this->validator->apply($transformed, $sheet);

                    if ($errors !== []) {
                        $this->rowErrorRepository->createMany($row->id, $errors);
                        $this->rowRepository->markAsFailedValidation($row->id);

                        continue;
                    }

                    $buffer[] = $this->prepareRowData($row, $transformed);
                } catch (Throwable $e) {
                    $this->rowErrorRepository->create($row->id, 'system', $e->getMessage());
                    $this->rowRepository->markAsFailed($row->id);
                }

                if (count($buffer) >= $this->batchSize) {
                    $this->rowRepository->bulkUpdate($buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                $this->rowRepository->bulkUpdate($buffer);
            }

            $this->rowChunkRepository->markAsCompleted($chunkId);

            if ($this->rowChunkRepository->allChunksProcessedForSheet($sheet->id)) {
                $this->sheetRepository->markAsCompleted($sheet->id);
            }
        } catch (Throwable $e) {
            $chunk->refresh();

            if ($chunk->status !== ExcelChunkStatus::COMPLETED) {
                $this->rowChunkRepository->markAsFailed($chunkId, $e->getMessage());
            }

            throw $e;
        }
    }

    private function prepareRowData(ExcelRow $row, array $transformed): array
    {
        return [
            'id' => $row->id,
            'excel_sheet_id' => $row->excel_sheet_id,
            'row_index' => $row->row_index,
            'content' => json_encode($transformed, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'status' => ExcelRowStatus::VALIDATED->value,
            'updated_at' => now(),
        ];
    }
}