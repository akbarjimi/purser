<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Listeners;

use Akbarjimi\Purser\Concerns\LogsImportActivity;
use Akbarjimi\Purser\Enums\LogLevel;
use Akbarjimi\Purser\Events\AllRowsExtracted;
use Akbarjimi\Purser\Events\FileProcessingCompleted;
use Akbarjimi\Purser\Jobs\ProcessChunkJob;
use Akbarjimi\Purser\Repositories\ExcelFileRepository;
use Akbarjimi\Purser\Services\ChunkerService;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Throwable;

final class HandleAllRowsExtracted implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;
    use LogsImportActivity;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        private readonly ChunkerService $chunker,
        private readonly ExcelFileRepository $fileRepository,
    ) {}

    public function viaQueue(): string
    {
        return config('purser.queue', 'default');
    }

    public function tags(): array
    {
        return ['excel-chunking'];
    }

    public function handle(AllRowsExtracted $event): void
    {
        $file = $this->fileRepository->findFile($event->fileId, ['excelSheets']);

        if ($file === null || $file->trashed()) {
            $this->importLog(LogLevel::WARNING, "File {$event->fileId} has been deleted. Skipping further processing.");

            return;
        }

        $fileId = $file->id;

        try {
            $chunks = $this->chunker->createChunksForFile($file);
        } catch (Throwable $e) {
            $this->fileRepository->markAsFailed($fileId, $e->getMessage());
            $this->importLog(LogLevel::CRITICAL, "Chunking failed for file {$fileId}. Error: {$e->getMessage()}");

            throw $e;
        }

        if ($chunks->isEmpty()) {
            $this->fileRepository->markAsProcessing($fileId);
            $this->fileRepository->markAsCompleted($fileId);
            FileProcessingCompleted::dispatch($fileId);

            $this->importLog(LogLevel::INFO, "No chunks created for file {$fileId} – marked as completed.");

            return;
        }

        $this->fileRepository->markAsProcessing($fileId);

        $jobs = $chunks->map(fn ($chunk) => new ProcessChunkJob($chunk->id))->all();

        Bus::batch($jobs)
            ->name("excel-process:{$fileId}")
            ->onQueue(config('purser.queue', 'default'))
            ->allowFailures(true)
            ->then(static function (Batch $batch) use ($fileId) {
                if ($batch->failedJobs > 0) {
                    return;
                }

                app(ExcelFileRepository::class)->markAsCompleted($fileId);
                FileProcessingCompleted::dispatch($fileId);

                //                $this->importLog(LogLevel::INFO, "Processing batch completed for file {$fileId}.", [
                //                    'batch_id' => $batch->id,
                //                ]);
            })
            ->catch(static function (Batch $batch, Throwable $e) use ($fileId) {
                app(ExcelFileRepository::class)->markAsFailed($fileId, $e->getMessage());

                //                $this->importLog(LogLevel::CRITICAL, "Processing batch failed for file {$fileId}. Error: {$e->getMessage()}");
            })
            ->finally(static function (Batch $batch) use ($fileId) {
                app(ExcelFileRepository::class)->recordBatchId($fileId, $batch->id);
            })
            ->dispatch();

        $this->importLog(LogLevel::INFO, "Chunk jobs batched for file {$fileId}.", [
            'count' => count($jobs),
        ]);
    }

    public function failed(AllRowsExtracted $event, Throwable $e): void
    {
        $this->fileRepository->markAsFailed($event->fileId, $e->getMessage());

        $this->importLog(LogLevel::CRITICAL, sprintf(
            '%s listener failed for file %d. Error: %s',
            self::class,
            $event->fileId,
            $e->getMessage(),
        ));
    }
}
