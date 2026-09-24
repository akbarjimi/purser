<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Listeners;

use Akbarjimi\Purser\Concerns\LogsImportActivity;
use Akbarjimi\Purser\Enums\LogLevel;
use Akbarjimi\Purser\Events\ExcelFileRegistered;
use Akbarjimi\Purser\Events\FileSheetsScanCompleted;
use Akbarjimi\Purser\Repositories\ExcelFileRepository;
use Akbarjimi\Purser\Repositories\ExcelSheetRepository;
use Akbarjimi\Purser\Services\SheetDiscoveryService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class HandleExcelFileRegistered implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;
    use LogsImportActivity;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private readonly SheetDiscoveryService $discoveryService,
        private readonly ExcelFileRepository $fileRepository,
        private readonly ExcelSheetRepository $sheetRepository,
    ) {}

    public function viaQueue(): string
    {
        return config('purser.queue', 'default');
    }

    public function tags(): array
    {
        return ['excel-registered'];
    }

    public function handle(ExcelFileRegistered $event): void
    {
        $file = $this->fileRepository->findFile($event->excelFileId);

        if ($file === null) {
            $this->importLog(LogLevel::WARNING, "File {$event->excelFileId} not found.");

            return;
        }

        if ($this->sheetRepository->existsForFile($file->id)) {
            $this->importLog(LogLevel::INFO, "Sheets already exist for file {$file->id}. Skipping discovery.");
            FileSheetsScanCompleted::dispatch($file->id);

            return;
        }

        $this->fileRepository->markAsReading($file->id);

        try {
            $sheets = $this->discoveryService->discover($file);

            if (empty($sheets)) {
                $this->importLog(LogLevel::WARNING, "No sheets found for file {$file->id}.");
                $this->fileRepository->markAsFailed($file->id, 'No sheets discovered');

                return;
            }

            $this->sheetRepository->bulkCreate($file->id, $sheets);
            $this->importLog(LogLevel::INFO, "Sheets discovered for file {$file->id}. Count: ".count($sheets), [
                'count' => count($sheets),
            ]);

            FileSheetsScanCompleted::dispatch($file->id);
        } catch (Throwable $e) {
            $this->fileRepository->markAsFailed($file->id, $e->getMessage());
            $this->importLog(LogLevel::ERROR, "Sheet discovery failed for file {$file->id}. Error: {$e->getMessage()}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(ExcelFileRegistered $event, Throwable $e): void
    {
        $this->fileRepository->markAsFailed($event->excelFileId, $e->getMessage());
        $this->importLog(LogLevel::CRITICAL, "Listener failed after retries for file {$event->excelFileId}. Error: {$e->getMessage()}", [
            'error' => $e->getMessage(),
        ]);
    }
}
