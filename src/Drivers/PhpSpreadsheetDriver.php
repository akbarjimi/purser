<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Drivers;

use Akbarjimi\Purser\Contracts\ExcelReaderDriver;
use Akbarjimi\Purser\Contracts\RowHandler;
use Akbarjimi\Purser\DTOs\RowData;
use Akbarjimi\Purser\DTOs\SheetInfo;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class PhpSpreadsheetDriver implements ExcelReaderDriver
{
    public function readRows(string $filePath, int $sheetIndex, RowHandler $handler): void
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $spreadsheet = $reader->load($filePath);

        try {
            $sheet = $spreadsheet->getSheet($sheetIndex);

            foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                $cells = [];
                foreach ($row->getCellIterator() as $cell) {
                    $cells[$cell->getColumn()] = $cell->getFormattedValue();
                }
                $handler->handle(new RowData($cells, $rowNumber - 1));
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public function listSheets(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $worksheetsInfo = $reader->listWorksheetInfo($filePath);

        return array_values(array_map(
            static fn (array $info, int $index): SheetInfo => SheetInfo::fromPhpSpreadsheet($info, $index),
            $worksheetsInfo,
            array_keys($worksheetsInfo),
        ));
    }
}
