# Purser

[![Latest Version on Packagist](https://img.shields.io/packagist/v/akbarjimi/purser.svg?style=flat-square)](https://packagist.org/packages/akbarjimi/purser)
[![Tests](https://img.shields.io/github/actions/workflow/status/akbarjimi/excel-importer-pipeline/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/akbarjimi/excel-importer-pipeline/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/akbarjimi/purser.svg?style=flat-square)](https://packagist.org/packages/akbarjimi/purser)

A distributed, event-driven, queue-based Excel import pipeline for Laravel.
Pluggable reader drivers, per-row validation, per-row error recovery, and a
handler you own.

## Why this exists

Most Excel packages treat an import as one operation: read the file, hand you
the rows, done. That works until one row fails validation halfway through a
50,000-row file, or a chunk job dies and you have no idea which rows committed,
or a reviewer asks for the audit trail of every rejected row.

This package treats the import as a multi-stage pipeline. Every stage is a
separate, retryable queued job. Every validation failure is recorded against the
row that caused it. The pipeline completes whether or not individual rows are
valid.

## Requirements

- PHP 8.1 or newer
- Laravel 10, 11, or 12
- One of:
  - `maatwebsite/excel` (PhpSpreadsheet driver, default)
  - `openspout/openspout` (streaming driver, lower memory)
- A database queue connection for production use

## Installation

```shell
    composer require akbarjimi/purser
    php artisan vendor:publish --tag=purser
    php artisan vendor:publish --tag=purser-sheets
    php artisan migrate
```

The first publish command installs `config/purser.php`. The second
installs `config/purser-sheets.php`, where you map spreadsheet columns
to your domain fields and declare validation rules per sheet.

## Quickstart

Define a handler. It receives a lazy stream of validated rows and does whatever
your application needs.

```php
    <?php

    declare(strict_types=1);

    namespace App\Imports;

    use Akbarjimi\Purser\Contracts\ImportHandler;
    use Akbarjimi\Purser\DTOs\ValidatedRow;
    use App\Models\User;

    final class UserImportHandler implements ImportHandler
    {
        public function handle(int $fileId, iterable $rows): void
        {
            foreach ($rows as $row) {
                assert($row instanceof ValidatedRow);

                User::updateOrCreate(
                    ['email' => $row->data['email']],
                    [
                        'name' => $row->data['name'],
                        'age' => $row->data['age'],
                    ],
                );
            }
        }
    }
```

Configure the sheet in `config/purser-sheets.php`.

```php
    return [
        'Users' => [
            'mapping' => [
                'name' => 'A',
                'email' => 'B',
                'age' => 'C',
            ],
            'validation' => [
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'age' => 'required|integer|min:18',
            ],
        ],
    ];

```

Dispatch the import.

```php
    use Akbarjimi\Purser\Services\ImportManager;
    use App\Imports\UserImportHandler;

    app(ImportManager::class)
        ->import('uploads/users.xlsx', disk: 's3')
        ->withHandler(UserImportHandler::class)
        ->dispatch();
```

The pipeline runs asynchronously. `excel:status {fileId}` shows progress.
`excel:retry {fileId}` re-dispatches failed chunks. Failed rows are available
through `ErrorReportService`.

## How it works

    ImportManager::import(path, disk)
      |
      +- PendingImport::dispatch()          creates ExcelFile, fires ExcelFileRegistered
      |
      +- HandleExcelFileRegistered          SheetDiscoveryService::discover()
      |                                       fires FileSheetsScanCompleted
      |
      +- HandleFileSheetsScanCompleted      extracts rows via ExtractSheetRowsJob batch
      |                                       fires AllRowsExtracted
      |
      +- HandleAllRowsExtracted             ChunkerService::createChunksForFile()
      |                                       dispatches ProcessChunkJob batch
      |                                       fires FileProcessingCompleted
      |
      +- InvokeImportHandler                resolves handler class from file meta
                                            streams ValidatedRow to your handler

Each stage is a queued job or listener. Each stage can fail and be retried
without re-running the ones before it. Each status transition is validated
against an enum state machine. There is no shared mutable state between stages.

## Reader drivers

The reading engine sits behind the `ExcelReaderDriver` contract. Two
implementations ship:

- `PhpSpreadsheetDriver` (default). Uses `maatwebsite/excel`. Loads the file
  into memory. Fine for files up to a few tens of thousands of rows.
- `OpenSpoutDriver`. Uses `openspout/openspout`. Streaming reader. Constant
  memory regardless of file size.

Select via `EXCEL_IMPORTER_DRIVER=openspout` or `excel-importer.driver`.
If the corresponding package is not installed, the driver throws
`MissingDriverDependencyException` at first use, naming the composer command
to run.

## Error recovery

Validation failures do not abort the import. Each rejected row is stored in
`excel_row_errors` with its field, type, code, and message. The chunk that
contains it completes normally; the file completes when every chunk has been
processed.

Retrieve the failures:

```php
    use Akbarjimi\Purser\Services\ErrorReportService;

    $service = app(ErrorReportService::class);

    $paginated = $service->paginate($fileId);
    $json      = $service->toJson($fileId);
    $spreadsheetPath = $service->toSpreadsheet($fileId, disk: 'local');

```

`excel:retry {fileId}` resets failed chunks and re-dispatches them. Only files
in `FAILED` status with at least one failed chunk are eligible.

## Configuration

The full config file lives at `config/purser.php` after publishing.
Selected keys:

| Key                 | Default       | Purpose                           |
|---------------------|---------------|-----------------------------------|
| `driver`            | `maatwebsite` | `maatwebsite` or `openspout`      |
| `chunk_size`        | `1000`        | Rows per processing chunk         |
| `insert_batch_size` | `100`         | Rows per database batch           |
| `hash_algo`         | `sha256`      | Row content hashing for dedup     |
| `max_sheets`        | `50`          | Reject files exceeding this       |
| `strict_validation` | `false`       | Throw on missing validation rules |
| `default_disk`      | `local`       | Storage disk for uploaded files   |
| `queue`             | `default`     | Queue connection for jobs         |

Per-sheet mapping and validation rules live in `config/purser-sheets.php`.

## Console commands

| Command                 | Purpose                                              |
|-------------------------|------------------------------------------------------|
| `excel:status {fileId}` | File, sheet, chunk, row counts, error count          |
| `excel:retry {fileId}`  | Reset failed chunks and re-dispatch                  |
| `excel:benchmark`       | Generate a fixture, run the pipeline, report timings |

## Testing

    composer test

## Documentation

- [Getting started](docs/getting-started.md)
- [Pipeline overview](docs/pipeline.md)
- [Writing a handler](docs/handlers.md)
- [Configuration reference](docs/configuration.md)
- [Validation and transformation](docs/validation-and-transformation.md)
- [Error recovery](docs/error-recovery.md)
- [Reader drivers](docs/drivers.md)
- [Console commands](docs/console-commands.md)
- [Upgrading from v0.x](docs/upgrading.md)

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security

Report vulnerabilities through the process in [SECURITY](SECURITY.md), not the
public issue tracker.

## License

MIT. See [LICENSE](LICENSE.md).
