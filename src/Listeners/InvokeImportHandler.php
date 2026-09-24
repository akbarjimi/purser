<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Listeners;

use Akbarjimi\Purser\Concerns\LogsImportActivity;
use Akbarjimi\Purser\Contracts\ImportHandler;
use Akbarjimi\Purser\Enums\LogLevel;
use Akbarjimi\Purser\Events\FileProcessingCompleted;
use Akbarjimi\Purser\Repositories\ExcelFileRepository;
use Akbarjimi\Purser\Repositories\ExcelRowRepository;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;

final class InvokeImportHandler implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;
    use LogsImportActivity;

    public function __construct(
        private readonly ExcelFileRepository $fileRepo,
        private readonly ExcelRowRepository $rowRepo,
    ) {}

    public function viaQueue(): string
    {
        return config('purser.queue', 'default');
    }

    public function tags(): array
    {
        return ['excel-handler'];
    }

    public function handle(FileProcessingCompleted $event): void
    {
        $handlerClass = $this->fileRepo->getHandler($event->fileId);

        if (! $handlerClass || ! class_exists($handlerClass)) {
            $this->importLog(LogLevel::WARNING, "No handler found for file {$event->fileId}.");

            return;
        }

        /** @var ImportHandler $handler */
        $handler = app($handlerClass);

        $rows = $this->rowRepo->getValidatedRowsForFile($event->fileId);

        $handler->handle($event->fileId, $rows);

        $this->importLog(LogLevel::INFO, "Handler invoked for file {$event->fileId}.");
    }
}
