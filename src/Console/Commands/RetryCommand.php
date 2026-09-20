<?php

declare(strict_types=1);

namespace Akbarjimi\ExcelImporter\Console\Commands;

use Akbarjimi\ExcelImporter\Enums\ExcelChunkStatus;
use Akbarjimi\ExcelImporter\Enums\ExcelFileStatus;
use Akbarjimi\ExcelImporter\Events\FileProcessingCompleted;
use Akbarjimi\ExcelImporter\Jobs\ProcessChunkJob;
use Akbarjimi\ExcelImporter\Models\ExcelFile;
use Akbarjimi\ExcelImporter\Models\ExcelRowChunk;
use Akbarjimi\ExcelImporter\Repositories\ExcelFileRepository;
use Akbarjimi\ExcelImporter\Repositories\ExcelRowChunkRepository;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

final class RetryCommand extends Command
{
    protected $signature = 'excel:retry {fileId : Excel file ID}';

    protected $description = 'Re-dispatch failed chunks, or re-invoke the import handler after a handler failure.';

    public function handle(
        ExcelFileRepository $fileRepository,
        ExcelRowChunkRepository $chunkRepository,
    ): int {
        $fileId = (int) $this->argument('fileId');
        $file = ExcelFile::withTrashed()->find($fileId);

        if ($file === null) {
            $this->error("File [{$fileId}] not found.");

            return self::FAILURE;
        }

        if ($file->trashed()) {
            $this->error("File [{$fileId}] is soft-deleted.");

            return self::FAILURE;
        }

        if ($file->status !== ExcelFileStatus::FAILED) {
            $this->error(sprintf(
                'File [%d] status is [%s]. Retry requires [%s].',
                $fileId,
                $file->status->value,
                ExcelFileStatus::FAILED->value,
            ));

            return self::FAILURE;
        }

        $failedChunkIds = ExcelRowChunk::query()
            ->whereHas('excelSheet', fn ($q) => $q->where('excel_file_id', $fileId))
            ->where('status', ExcelChunkStatus::FAILED->value)
            ->pluck('id')
            ->all();

        if ($failedChunkIds === []) {
            return $this->retryHandler($fileRepository, $fileId);
        }

        $fileRepository->markAsProcessing($fileId);

        foreach ($failedChunkIds as $chunkId) {
            $chunkRepository->markAsPending($chunkId);
        }

        $jobs = array_map(
            static fn (int $id): ProcessChunkJob => new ProcessChunkJob($id),
            $failedChunkIds,
        );

        Bus::batch($jobs)
            ->name("excel-retry:{$fileId}")
            ->onQueue(config('excel-importer.queue', 'default'))
            ->allowFailures(true)
            ->then(static function (Batch $batch) use ($fileId): void {
                if ($batch->failedJobs > 0) {
                    app(ExcelFileRepository::class)->markAsFailed(
                        $fileId,
                        "Retry failed: {$batch->failedJobs} chunks still failing.",
                    );

                    return;
                }

                // COMPLETED is set by InvokeImportHandler after the domain write succeeds.
                FileProcessingCompleted::dispatch($fileId);
            })
            ->catch(static function (Batch $batch, Throwable $e) use ($fileId): void {
                app(ExcelFileRepository::class)->markAsFailed($fileId, $e->getMessage());
            })
            ->finally(static function (Batch $batch) use ($fileId): void {
                app(ExcelFileRepository::class)->recordBatchId($fileId, $batch->id);
            })
            ->dispatch();

        $this->info(sprintf(
            'Reset %d chunks. Dispatched %d retry jobs for file %d.',
            count($failedChunkIds),
            count($jobs),
            $fileId,
        ));

        return self::SUCCESS;
    }

    private function retryHandler(ExcelFileRepository $fileRepository, int $fileId): int
    {
        $fileRepository->markAsProcessing($fileId);
        FileProcessingCompleted::dispatch($fileId);

        $this->info(sprintf(
            'No failed chunks. Re-dispatched import handler for file %d.',
            $fileId,
        ));

        return self::SUCCESS;
    }
}
