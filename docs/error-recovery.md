# Error Recovery

The pipeline is designed so a single bad row cannot abort an import. This is
the package's core value. Read this page before deploying to production.

## What counts as a failure

Two failure classes exist, and they are handled differently.

### Validation failure (per row, expected)

A row was transformed successfully but failed one or more rules from
`config/excel-importer-sheets.php`. The row is marked
`ExcelRowStatus::FAILED_VALIDATION`, one `ExcelRowError` per rule violation is
written, and the chunk continues with the next row.

Nothing about this aborts the import. A file where every row failed validation
still transitions to `COMPLETED`.

### System failure (per chunk, unexpected)

The transformer threw, or JSON encoding failed, or a database write failed,
or something else in the pipeline misbehaved. Two sub-cases:

- **Transformer or JSON failure on a single row.** The row is marked
  `ExcelRowStatus::FAILED`, a single `ExcelRowError` with `error_type: system`
  is written, and the chunk continues.
- **Failure of `bulkUpdate` or an infrastructure call.** The chunk is marked
  `ExcelChunkStatus::FAILED` with the exception message. The job rethrows so
  Laravel's retry mechanism can reschedule it. Already-written row errors for
  this chunk are preserved.

## How failures are recorded

`ExcelRowError` has five fields:

| Column         | Meaning                                                        |
|----------------|----------------------------------------------------------------|
| `excel_row_id` | The `excel_rows.id` of the failed row                          |
| `field`        | Column letter or mapped field name; `null` for system failures |
| `error_type`   | `validation` or `system`                                       |
| `error_code`   | Optional machine code; `null` by default                       |
| `message`      | Human-readable description                                     |

Rows are not deleted when they fail. Both `excel_rows` and `excel_row_errors`
are retained for the audit trail until you prune them.

## Reading the failures

`ErrorReportService` exposes three retrieval modes plus a paginated view.

    <?php

    declare(strict_types=1);

    use Akbarjimi\ExcelImporter\Services\ErrorReportService;

    $service = app(ErrorReportService::class);

    // Paginated ExcelRow models, ordered by id, eager-loaded with errors.
    $page = $service->paginate($fileId, perPage: 50);

    // All failed rows, in one collection.
    $rows = $service->all($fileId);

    // JSON string, safe to return from an HTTP endpoint.
    $json = $service->toJson($fileId);

    // Excel spreadsheet written to the given disk. Returns the path.
    $path = $service->toSpreadsheet($fileId, disk: 'local');

`toJson()` produces a structure like this:

    [
      {
        "row_index": 42,
        "data": { "name": "Broken", "email": "not-an-email", "age": 15 },
        "errors": [
          {
            "field": "email",
            "type": "validation",
            "code": null,
            "message": "The email must be a valid email address."
          },
          {
            "field": "age",
            "type": "validation",
            "code": null,
            "message": "The age must be at least 18."
          }
        ]
      }
    ]

`toSpreadsheet()` requires `openspout/openspout`. If the package is not
installed it throws a `RuntimeException` naming the composer command.

## The retry workflow

`excel:retry` only applies to files whose status is `ExcelFileStatus::FAILED`.
Preconditions:

1. The file exists and is not soft-deleted.
2. The file's status is `FAILED`.

### Chunk-level failure

If at least one chunk has status `ExcelChunkStatus::FAILED`, the command:

1. Marks the file `PROCESSING`.
2. Resets every failed chunk to `PENDING` and clears its `error` column.
3. Dispatches a `Bus::batch` of `ProcessChunkJob` for those chunks.
4. On success: fires `FileProcessingCompleted` (the handler marks `COMPLETED`).
5. On failure: marks the file `FAILED` with a count of the still-failing chunks.

Only failed chunks are re-run. Completed chunks are untouched. This is why
`ProcessChunkJob` is idempotent: it checks the chunk status and returns
immediately if already `COMPLETED`.

### Handler-level failure

If validation/chunking succeeded but `InvokeImportHandler` failed (timeout,
exception, missing handler class), there are no failed chunks. The file is
still `FAILED` because completion is deferred until the handler returns.

In that case `excel:retry`:

1. Marks the file `PROCESSING`.
2. Re-dispatches `FileProcessingCompleted` so the handler runs again.

   php artisan excel:retry 42

## When the file becomes COMPLETED

Chunk processing finishing is not the end of the import. The file stays
`PROCESSING` until `InvokeImportHandler` finishes successfully, then
transitions to `COMPLETED`. If the handler throws after retries are
exhausted, the file becomes `FAILED` and is eligible for `excel:retry`.

## When retry will not help

If the failure was caused by data that will not change — a malformed file, a
missing transformer class, a validation rule that rejects every row — retrying
will fail again. The `error` column on `excel_row_chunks` (or on `excel_files`
for handler failures) names the cause.

For validation failures, retry never applies: the chunk did not fail. The file
completed with partial success. Inspect the errors, fix the source data in
your application, and re-import the file.

For system failures caused by external factors — a database that was down, a
transformer that had a bug — fix the cause, deploy, then retry.

## Auditing the pipeline

Three commands give an operator full visibility.

    php artisan excel:status 42

Prints file metadata, per-sheet status, chunk counts by status, row counts by
status, and the total error count for the file.

    php artisan excel:retry 42

Re-runs failed chunks as described above.

    php artisan excel:benchmark

Generates a synthetic file, runs it through the pipeline, reports phase
timings and peak memory. Use this to compare drivers or tune chunk size.

## Pruning the audit trail

Failed rows are retained indefinitely. Prune them on your own schedule:

    // Delete errors for a file after your operators have reviewed them.
    ExcelRowError::whereHas(
        'excelRow.excelSheet',
        fn ($q) => $q->where('excel_file_id', $fileId),
    )->delete();

Cascade rules ensure deleting an `ExcelFile` removes its sheets, rows, chunks,
and errors.