<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Services;

use Akbarjimi\Purser\Concerns\LogsImportActivity;
use Akbarjimi\Purser\Contracts\ExcelReaderDriver;
use Akbarjimi\Purser\Contracts\RowExtractorInterface;
use Akbarjimi\Purser\Enums\LogLevel;
use Akbarjimi\Purser\Models\ExcelSheet;
use Akbarjimi\Purser\Repositories\ExcelRowRepository;
use Akbarjimi\Purser\Repositories\ExcelSheetRepository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Throwable;

final class RowExtractionService implements RowExtractorInterface
{
    use LogsImportActivity;

    public function __construct(
        private readonly ExcelReaderDriver $readerDriver,
        private readonly ExcelRowRepository $rowRepository,
        private readonly ExcelSheetRepository $sheetRepository,
        private readonly FilesystemFactory $filesystem,
        private readonly LocalFileResolver $fileResolver,
        private readonly int $batchSize,
        private readonly string $hashAlgo,
    ) {}

    public function extract(ExcelSheet $sheet): int
    {
        $this->sheetRepository->markAsExtracting($sheet->id);

        $file = $sheet->excelFile;
        $disk = $this->filesystem->disk($file->disk);
        $localPath = $this->fileResolver->resolve($disk, $file->path);
        $isTemp = $localPath !== $disk->path($file->path);

        try {
            $buffer = new SheetRowBuffer(
                $sheet->id,
                $this->rowRepository,
                $this->hashAlgo,
                $this->batchSize,
            );

            $this->readerDriver->readRows(
                $localPath,
                $sheet->sheet_index,
                $buffer,
            );

            $buffer->flush();

            $this->sheetRepository->markAsExtracted($sheet->id);

            $this->importLog(LogLevel::INFO, "Extracted {$buffer->inserted()} rows from sheet {$sheet->id}.", [
                'sheet_id' => $sheet->id,
                'rows' => $buffer->inserted(),
            ]);

            return $buffer->inserted();
        } catch (Throwable $e) {
            $this->sheetRepository->markAsFailed($sheet->id, $e->getMessage());

            $this->importLog(LogLevel::CRITICAL, "Extraction failed for sheet {$sheet->id}. Error: {$e->getMessage()}", [
                'sheet_id' => $sheet->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($isTemp && is_file($localPath)) {
                @unlink($localPath);
            }
        }
    }
}
