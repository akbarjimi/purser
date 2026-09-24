<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Listeners;

use Akbarjimi\Purser\Concerns\LogsImportActivity;
use Akbarjimi\Purser\Enums\LogLevel;
use Akbarjimi\Purser\Events\AllRowsExtracted;
use Akbarjimi\Purser\Events\FileSheetsScanCompleted;
use Akbarjimi\Purser\Jobs\ExtractSheetRowsJob;
use Akbarjimi\Purser\Repositories\ExcelFileRepository;
use Akbarjimi\Purser\Repositories\ExcelSheetRepository;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HandleFileSheetsScanCompleted implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;
    use LogsImportActivity;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private readonly ExcelSheetRepository $sheetRepo,
        private readonly ExcelFileRepository $fileRepo,
    ) {}

    public function viaQueue(): string
    {
        return config('purser.queue', 'default');
    }

    public function tags(): array
    {
        return ['excel-sheets-scan'];
    }

    public function handle(FileSheetsScanCompleted $event): void
    {
        $sheets = $this->sheetRepo->getByFileId($event->fileId);

        $limit = (int) config('purser.max_sheets', 50);
        if ($sheets->count() > $limit) {
            $message = "File contains {$sheets->count()} sheets, which exceeds the maximum of {$limit}.";
            $this->fileRepo->markAsFailed($event->fileId, $message);

            $this->importLog(LogLevel::WARNING, $message, [
                'count' => $sheets->count(),
                'limit' => $limit,
            ]);

            return;
        }

        $fileId = $event->fileId;
        $jobs = $sheets->map(fn ($sheet) => new ExtractSheetRowsJob($sheet->id))->all();

        if (empty($jobs)) {
            $this->fileRepo->markAsRowsExtracted($fileId);
            AllRowsExtracted::dispatch($fileId);
            $this->importLog(LogLevel::INFO, "No sheets to extract for file {$fileId}.");

            return;
        }

        Bus::batch($jobs)
            ->name("excel-extract:{$fileId}")
            ->onQueue(config('purser.queue', 'default'))
            ->allowFailures(false)
            ->then(static function (Batch $batch) use ($fileId) {
                app(ExcelFileRepository::class)->markAsRowsExtracted($fileId);
                AllRowsExtracted::dispatch($fileId);
                Log::info("All sheets extracted for file {$fileId}.", ['batch_id' => $batch->id]);
            })
            ->catch(static function (Batch $batch, Throwable $e) use ($fileId) {
                app(ExcelFileRepository::class)->markAsFailed($fileId, $e->getMessage());
                Log::critical("Extraction batch failed for file {$fileId}.", [
                    'error' => $e->getMessage(),
                ]);
            })
            ->finally(static function (Batch $batch) use ($fileId) {
                app(ExcelFileRepository::class)->recordBatchId($fileId, $batch->id);
            })
            ->dispatch();
    }
}
