<?php

declare(strict_types=1);

namespace Akbarjimi\ExcelImporter\Drivers;

use Akbarjimi\ExcelImporter\Contracts\ExcelReaderDriver;
use Akbarjimi\ExcelImporter\Contracts\RowHandler;
use Akbarjimi\ExcelImporter\DTOs\RowData;
use Akbarjimi\ExcelImporter\DTOs\SheetInfo;
use Akbarjimi\ExcelImporter\Exceptions\MissingDriverDependencyException;
use OpenSpout\Reader\XLSX\Reader;

final class OpenSpoutDriver implements ExcelReaderDriver
{
    public function readRows(string $filePath, int $sheetIndex, RowHandler $handler): void
    {
        $this->ensureInstalled();

        if (! is_file($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $reader = new Reader;
        $reader->open($filePath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getIndex() !== $sheetIndex) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $handler->handle(new RowData(
                        cells: $this->normalizeRow($row->toArray()),
                        rowNumber: $rowNumber - 1,
                    ));
                }

                return;
            }

            throw new \RuntimeException("Sheet index {$sheetIndex} not found in [{$filePath}].");
        } finally {
            $reader->close();
        }
    }

    public function listSheets(string $filePath): array
    {
        $this->ensureInstalled();

        if (! is_file($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $reader = new Reader;
        $reader->open($filePath);

        try {
            $sheets = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheets[] = new SheetInfo(
                    name: $sheet->getName(),
                    index: $sheet->getIndex(),
                    totalRows: 0,
                    totalColumns: 0,
                    raw: ['name' => $sheet->getName()],
                );
            }

            return $sheets;
        } finally {
            $reader->close();
        }
    }

    private function ensureInstalled(): void
    {
        if (! class_exists(Reader::class)) {
            throw MissingDriverDependencyException::for('openspout', 'openspout/openspout');
        }
    }

    private function normalizeRow(array $cells): array
    {
        $result = [];
        $index = 0;

        foreach ($cells as $value) {
            $result[$this->columnLetter($index)] = $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value;
            $index++;
        }

        return $result;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
