# Getting Started

From `composer require` to a working import in ten minutes.

## 1. Install the package

composer require akbarjimi/purser

Pick a reader driver. The default is `maatwebsite/excel`:

composer require maatwebsite/excel

For lower memory on large files, install OpenSpout instead:

composer require openspout/openspout

You can install both and switch with an environment variable later.

## 2. Publish config and run migrations

php artisan vendor:publish --tag=purser
php artisan vendor:publish --tag=purser-sheets
php artisan migrate

Two config files are published:

- `config/purser.php` — global pipeline settings
- `config/purser-sheets.php` — per-sheet mapping, transformation, validation

Five tables are created: `excel_files`, `excel_sheets`, `excel_rows`,
`excel_row_chunks`, `excel_row_errors`.

## 3. Map a sheet

Given a spreadsheet with columns A, B, C and headers on row 1, open
`config/purser-sheets.php` and add an entry keyed by sheet name:

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

The mapping keys are the field names your handler will see. The values are
column letters.

If a sheet has no `mapping`, every column is passed through keyed by its
letter. If it has no `validation`, no validation runs — unless
`strict_validation` is enabled, in which case a missing rule set throws.

## 4. Write a handler

A handler receives the file ID and a lazy iterable of `ValidatedRow`
instances. It is called once per file, after every chunk has been processed.

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
                    'age'  => $row->data['age'],
                ],
            );
        }
    }
}

Every row in the stream passed validation. Rows that failed are stored in
`excel_row_errors` and never reach the handler.

## 5. Dispatch an import

    use Akbarjimi\Purser\Services\ImportManager;
    use App\Imports\UserImportHandler;

    $file = app(ImportManager::class)
        ->import('uploads/users.xlsx', disk: 's3')
        ->withHandler(UserImportHandler::class)
        ->dispatch();

`import()` accepts a path relative to the storage disk and an optional disk
name. If the disk is omitted, `purser.default_disk` is used, falling
back to `filesystems.default`.

`withHandler()` accepts a class-string of anything implementing
`ImportHandler`. The class is instantiated through the container, so it can
receive its own dependencies via constructor injection.

`dispatch()` writes an `ExcelFile` row and fires `ExcelFileRegistered`. From
that point the pipeline runs on the queue.

You can attach arbitrary metadata to the file record:

    ->withMeta(['uploaded_by' => $request->user()->id])

Metadata is stored in the `meta` JSON column and available to any listener or
command that reads the file.

## 6. Verify

If your queue connection is `sync` (the default in a fresh Laravel app), the
import completes before `dispatch()` returns. Check the file status:

    php artisan excel:status 1

To use a real queue, set `QUEUE_CONNECTION=database` (or `redis`) and run a
worker:

    php artisan queue:work

## 7. Inspect errors

If any rows failed validation:

    use Akbarjimi\Purser\Services\ErrorReportService;

    $service = app(ErrorReportService::class);

    $page = $service->paginate($file->id);          // paginated ExcelRow models
    $json = $service->toJson($file->id);            // JSON string
    $path = $service->toSpreadsheet($file->id);     // .xlsx on the local disk

See `docs/error-recovery.md` for the full workflow.

## Next steps

- `docs/handlers.md` — handler patterns, transactions, streaming
- `docs/error-recovery.md` — retry, audit, error reporting
- `docs/drivers.md` — choosing between PhpSpreadsheet and OpenSpout
- `docs/console-commands.md` — status, retry, benchmark
- `docs/configuration.md` — every key in both config files