<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Jobs;

use Akbarjimi\Purser\Repositories\ExcelSheetRepository;
use Akbarjimi\Purser\Services\RowExtractionService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;

final class ExtractSheetRowsJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly int $sheetId) {}

    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function tags(): array
    {
        return ['excel-extract', "sheet:{$this->sheetId}"];
    }

    public function handle(
        RowExtractionService $extraction,
        ExcelSheetRepository $sheetRepo,
    ): void {
        $sheet = $sheetRepo->getById($this->sheetId);

        // If sheet is soft-deleted or missing, skip.
        if (! $sheet || $sheet->trashed()) {
            return;
        }

        $extraction->extract($sheet);
    }
}
