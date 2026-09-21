# Reader Drivers

A driver reads rows from a spreadsheet and returns them to the pipeline. Two
implementations ship with the package. You can add your own.

## The contract

    <?php

    declare(strict_types=1);

    namespace Akbarjimi\ExcelImporter\Contracts;

    use Akbarjimi\ExcelImporter\DTOs\SheetInfo;

    interface ExcelReaderDriver
    {
        /**
         * Read rows from a sheet and dispatch each as a RowData to the handler.
         *
         * Implementations MUST invoke $handler->handle(new RowData(...)) for every row.
         */
        public function readRows(string $filePath, int $sheetIndex, RowHandler $handler): void;

        /**
         * @return list<SheetInfo>
         */
        public function listSheets(string $filePath): array;
    }

Two implementations exist because the two available packages make different
engineering trade-offs. The contract hides the difference from the rest of the
pipeline.

## `PhpSpreadsheetDriver` (default)

Uses `maatwebsite/excel`, which wraps `PhpOffice\PhpSpreadsheet`.

    'driver' => 'maatwebsite',

### How it reads

`readRows()` calls `IOFactory::createReaderForFile()` and `load()`. The entire
file is loaded into memory as a `Spreadsheet` object. Every cell is a PHP
object with formatting, style, and formula state. Rows are iterated from the
in-memory object.

`listSheets()` uses `listWorksheetInfo()`, which returns actual row and column
counts without loading cell data.

### Memory profile

Roughly 1 KB per cell. A 50,000-row file with 10 columns consumes about 500 MB
during extraction. This is a property of the upstream library, not this
package.

### Strengths

- Accurate `totalRows` and `totalColumns` on `listSheets`.
- Handles `.xls`, `.xlsx`, `.ods`, `.csv`.
- Formula evaluation available if you extend the driver.
- Well-tested upstream. If it works with the wrapper library, it works here.

### Weaknesses

- Memory grows with file size.
- Slower than OpenSpout on the same file.
- Requires `maatwebsite/excel` in `composer.json`.

### When to use

- Files under 10,000 rows.
- Files where you need accurate row counts before extraction.
- Projects that already depend on `maatwebsite/excel`.

## `OpenSpoutDriver`

Uses `openspout/openspout`.

    'driver' => 'openspout',

### How it reads

`readRows()` opens a streaming `Reader`, iterates the sheet iterator until it
finds the requested index, then iterates rows. Each row is converted to an
array of cells and passed to the handler immediately. Nothing is retained.

`listSheets()` iterates the sheet iterator and returns `SheetInfo` for each
sheet. OpenSpout does not expose row or column counts without consuming the
entire sheet, so this driver returns `totalRows: 0` and `totalColumns: 0`.

### Memory profile

Constant. A 50,000-row file and a 5,000,000-row file consume the same memory
during extraction — the size of a single row.

### Strengths

- Streaming. Flat memory regardless of file size.
- Faster than PhpSpreadsheet on the same file.
- Actively maintained by a small team with a narrow focus.

### Weaknesses

- `listSheets()` returns zeroed counts. If you display "rows to be imported"
  before extraction, this driver cannot provide that number.
- Only `.xlsx` and `.csv`. No `.xls`, no `.ods`.
- `totalColumns` is always `0`.
- Throws `MissingDriverDependencyException` if `openspout/openspout` is not
  installed.

### When to use

- Files over 10,000 rows.
- Files where memory is a constraint on the queue worker.
- Projects that do not need accurate row counts before extraction.

## The `totalRows` divergence

This is a real, intentional difference between the two drivers. It is
documented here because it will surprise you if you display progress before
extraction.

| Driver                 | `totalRows` | `totalColumns` |
|------------------------|-------------|----------------|
| `PhpSpreadsheetDriver` | Accurate    | Accurate       |
| `OpenSpoutDriver`      | `0`         | `0`            |

If you need accurate counts, either use PhpSpreadsheet, or open the file once
with PhpSpreadsheet to get the counts and then switch the driver for
extraction. This is not supported by the pipeline; it is a workaround if you
have a specific need.

Do not attempt to make `OpenSpoutDriver::listSheets` count rows. It would
require reading the entire sheet to produce a number that PhpSpreadsheet
already returns from metadata without any reading. That defeats the driver's
purpose.

## Switching drivers

Set the environment variable or the config value:

    EXCEL_IMPORTER_DRIVER=openspout

Or in `config/excel-importer.php`:

    'driver' => 'openspout',

Only one driver is active at a time. If you want to read some sheets with
PhpSpreadsheet and others with OpenSpout, that requires custom code — the
pipeline does not support per-sheet driver selection.

## Writing a custom driver

Implement `ExcelReaderDriver` and register it in `excel-importer.drivers`:

    'drivers' => [
        'maatwebsite' => PhpSpreadsheetDriver::class,
        'openspout'   => OpenSpoutDriver::class,
        'csv'         => App\Excel\CsvDriver::class,
    ],

Then set `driver` to `csv`.

Requirements for a driver:

1. It must implement `readRows()` and call `$handler->handle(new RowData(...))`
   for every row. Skipping rows silently is a bug.
2. `RowData::$cells` must be keyed by column letter (`'A'`, `'B'`, `'AA'`).
   The pipeline's mapping config uses column letters.
3. `RowData::$rowNumber` must be 0-based.
4. `listSheets()` must return a `list<SheetInfo>`.

If the driver depends on an external package, throw
`MissingDriverDependencyException::for($key, $package)` in the constructor or
at the top of every method. Do not let a `Class not found` error reach the
user — the exception names the composer command they need.

## Throwing on missing dependencies

Both shipped drivers guard their entry points:

    use Akbarjimi\ExcelImporter\Exceptions\MissingDriverDependencyException;

    private function ensureInstalled(): void
    {
        if (! class_exists(Reader::class)) {
            throw MissingDriverDependencyException::for('openspout', 'openspout/openspout');
        }
    }

The exception message includes the install command. Follow this pattern in
custom drivers.