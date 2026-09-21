# Pipeline Overview

An import runs through six stages. Each stage is a queued job or event
listener. Each stage persists its output before signalling the next. Nothing
is held in memory between stages.

## The stages

### 1. Registration

`ImportManager::import()` returns a `PendingImport` builder. `dispatch()`
validates that a handler is set and the file exists, then creates an
`ExcelFile` row inside a transaction and fires `ExcelFileRegistered`.

`ExcelFileRegistered` implements `ShouldDispatchAfterCommit`, so the listener
runs only after the row is committed and visible to the queue worker.

    ImportManager::import(path, disk)
      +-- PendingImport::withHandler(Class)
      +-- PendingImport::withMeta(array)
      +-- PendingImport::dispatch()
            +-- create ExcelFile (status: PENDING)
            +-- fire ExcelFileRegistered

### 2. Sheet discovery

`HandleExcelFileRegistered` calls `SheetDiscoveryService::discover()`, which
resolves the file to a local path via `LocalFileResolver`, then asks the
configured `ExcelReaderDriver` for a list of `SheetInfo`. The sheets are
persisted via `ExcelSheetRepository::bulkCreate()`, and the file transitions
to `READING`. On success, `FileSheetsScanCompleted` is fired.

If the file already has sheets — which happens on a re-dispatch — the listener
skips discovery and fires `FileSheetsScanCompleted` immediately.

If the file has more sheets than `excel-importer.max_sheets`, the file is
marked `FAILED` and no further processing occurs.

### 3. Row extraction

`HandleFileSheetsScanCompleted` dispatches a `Bus::batch` of
`ExtractSheetRowsJob`, one per sheet. Each job calls
`RowExtractionService::extract()`, which streams rows from the driver through
`SheetRowBuffer` and upserts them into `excel_rows` in batches of
`insert_batch_size`.

The batch runs with `allowFailures(false)`. If any sheet fails, the whole
extraction batch fails and the file is marked `FAILED`.

On success, the batch's `then` callback marks the file `ROWS_EXTRACTED` and
fires `AllRowsExtracted`.

    ExtractSheetRowsJob (one per sheet)
      +-- RowExtractionService::extract(sheet)
            +-- markAsExtracting
            +-- readerDriver->readRows()
            +-- SheetRowBuffer::handle() per row
            +-- flush() every insert_batch_size rows
            +-- markAsExtracted

### 4. Chunking

`HandleAllRowsExtracted` calls `ChunkerService::createChunksForFile()`, which
walks every sheet's rows in slices of `chunk_size` and inserts one
`ExcelRowChunk` per slice, keyed by `from_row_id` and `to_row_id`. Each sheet
transitions to `CHUNKS_DISPATCHED`.

Chunking runs inside a database transaction with three retries. If chunking
fails, the file is marked `FAILED`.

If no chunks are produced — an empty file — the file is marked `COMPLETED`
and `FileProcessingCompleted` is fired immediately.

### 5. Processing

`HandleAllRowsExtracted` dispatches a second `Bus::batch` of
`ProcessChunkJob`, one per chunk. The batch runs with `allowFailures(true)`,
so individual chunk failures do not abort the batch.

`ProcessChunkJob::handle()` calls `ChunkProcessor::process()`. That service:

1. Guards against a missing sheet and a soft-deleted file, failing the chunk
   if either is found.
2. Marks the chunk `PROCESSING`.
3. Streams the rows in its ID range through `TransformService` and
   `ValidateService`.
4. Records validation failures in `excel_row_errors` and marks the row
   `FAILED_VALIDATION`.
5. Records transformer exceptions in `excel_row_errors` with type `system`
   and marks the row `FAILED`.
6. Buffers valid rows and bulk-updates them to `VALIDATED` every
   `insert_batch_size`.
7. Marks the chunk `COMPLETED`.
8. Marks the sheet `COMPLETED` when every chunk on it is `COMPLETED`.

When the batch finishes, its `then` callback marks the file `COMPLETED` and
fires `FileProcessingCompleted`.

### 6. Handler invocation

`InvokeImportHandler` reads the handler class from `ExcelFile.meta.handler`,
resolves it through the container, and calls `handle($fileId, $rows)` with a
lazy iterable of `ValidatedRow`. The iterable is a `LazyCollection` — rows are
streamed from the database, not loaded.

## Events

| Event                     | Fired by                                 | Payload            | Meaning                                |
|---------------------------|------------------------------------------|--------------------|----------------------------------------|
| `ExcelFileRegistered`     | `PendingImport::dispatch`                | `int $excelFileId` | File row created, pipeline starts      |
| `FileSheetsScanCompleted` | `HandleExcelFileRegistered`              | `int $fileId`      | Sheets discovered and persisted        |
| `AllRowsExtracted`        | `HandleFileSheetsScanCompleted`          | `int $fileId`      | Every sheet's rows are in `excel_rows` |
| `FileProcessingCompleted` | `HandleAllRowsExtracted`, `RetryCommand` | `int $fileId`      | All chunks processed, handler invoked  |

All four are dispatched after commit.

## Status machines

Each entity has an enum with `canTransitionTo()`. Illegal transitions throw
`RuntimeException` from `HasStatusTransitions`. Idempotent transitions — from
a state to itself — are no-ops.

### File

    PENDING -> READING | FAILED
    READING -> ROWS_EXTRACTING | ROWS_EXTRACTED | FAILED
    ROWS_EXTRACTING -> ROWS_EXTRACTED | FAILED
    ROWS_EXTRACTED -> PROCESSING | FAILED
    PROCESSING -> COMPLETED | FAILED
    FAILED -> PROCESSING   (retry)
    COMPLETED -> (terminal)

### Sheet

    PENDING -> EXTRACTING | FAILED
    EXTRACTING -> EXTRACTED | FAILED
    EXTRACTED -> CHUNKS_DISPATCHED | EXTRACTING | FAILED
    CHUNKS_DISPATCHED -> COMPLETED | FAILED
    COMPLETED, FAILED -> (terminal)

### Chunk

    PENDING -> PROCESSING | FAILED
    PROCESSING -> COMPLETED | FAILED
    FAILED -> PENDING   (retry)
    COMPLETED -> (terminal)

### Row

    PENDING -> VALIDATED | FAILED_VALIDATION | FAILED
    VALIDATED -> PROCESSED | FAILED
    FAILED_VALIDATION, PROCESSED, FAILED -> (terminal)

`PROCESSED` is defined but not currently set by the pipeline. It exists for
downstream consumers that want to mark rows after their handler has processed
them.

## Idempotency

Every stage is designed to be safe under re-execution.

- `HandleExcelFileRegistered` skips discovery if sheets already exist.
- `SheetRowBuffer` upserts by `(excel_sheet_id, content_hash, hash_algo)`, so
  re-extraction produces the same row set.
- `ProcessChunkJob` returns immediately if the chunk is already `COMPLETED`.
- `ChunkerService` is wrapped in `DB::transaction(..., 3)` and keyed by unique
  `(excel_sheet_id, from_row_id, to_row_id)`.

`excel:retry` relies on this: it resets failed chunks to `PENDING` and
re-dispatches them without touching anything else.

## Queue and batching

Two batches run per import:

| Batch name               | Jobs          | `allowFailures` |
|--------------------------|---------------|-----------------|
| `excel-extract:{fileId}` | one per sheet | false           |
| `excel-process:{fileId}` | one per chunk | true            |

Both are dispatched onto `excel-importer.queue`. The batch IDs are recorded
on the `ExcelFile.batch_id` column by the batch's `finally` callback.

Because `allowFailures(true)` is used for the processing batch, a chunk can
fail while the rest continue. The file still transitions to `COMPLETED`; the
failed chunk is visible in `excel:status` and can be retried with
`excel:retry`.