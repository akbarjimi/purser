# Changelog

All notable changes to `laravel-excel-importer` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-20

First stable release.

### Renamed

- Package renamed from `laravel-excel-importer` to `purser`. Namespace changed
  from `Akbarjimi\ExcelImporter` to `Akbarjimi\Purser`. Config files renamed
  from `excel-importer.php` to `purser.php` and `excel-importer-sheets.php`
  to `purser-sheets.php`.

### Added

- Event-driven import pipeline with four stages: sheet discovery, row extraction,
  chunking, chunk processing. Each stage is isolated, retryable, and observable
  through Laravel's event system.
- Pluggable reader drivers. `PhpSpreadsheetDriver` (default, based on
  `maatwebsite/excel`) and `OpenSpoutDriver` (low-memory, streaming). Selected via
  `excel-importer.driver`.
- Row-level error persistence. Validation failures and transformer exceptions are
  recorded in `excel_row_errors` per row. A chunk can complete successfully while
  individual rows fail without aborting the batch.
- `ErrorReportService` for retrieving failed rows as a paginated collection, as
  JSON, or as an `.xlsx` spreadsheet.
- Status state machines on files, sheets, rows, and chunks. Illegal transitions
  throw `RuntimeException` via `HasStatusTransitions`.
- Console commands: `excel:status`, `excel:retry`, `excel:benchmark`.
- `ImportManager` fluent entry point with `PendingImport` builder.
- Migrations for `excel_files`, `excel_sheets`, `excel_rows`, `excel_row_chunks`,
  `excel_row_errors`.
- Full Pest test suite with CI matrix against PHP 8.1–8.4 × Laravel 10–12.

### Fixed

- `ChunkProcessor::process` no longer wraps the row loop in a single database
  transaction. Prior behaviour: if a later `bulkUpdate` threw, `DB::rollBack()`
  discarded `excel_row_errors` written earlier in the chunk, losing the audit
  trail for rows that had already failed validation.
- `ChunkProcessor::process` guards against a missing `ExcelSheet`. Prior
  behaviour: a hard-deleted sheet caused a fatal `Error` on
  `$sheet->excelFile->trashed()`, and the chunk was never marked failed.
- `OpenSpoutDriver::readRows` and `OpenSpoutDriver::listSheets` throw
  `MissingDriverDependencyException` when `openspout/openspout` is not installed.
  Prior behaviour: a raw `Error: Class "OpenSpout\Reader\XLSX\Reader" not found`.

### Changed

- `ChunkProcessor::process` marks a chunk `FAILED` with a diagnostic message
  when the underlying sheet row is missing, instead of throwing.
- `OpenSpoutDriver::listSheets` returns `totalRows: 0` and `totalColumns: 0`.
  OpenSpout is a streaming reader and cannot pre-count without consuming the
  sheet. Consumers requiring accurate counts on `listSheets` must use
  `PhpSpreadsheetDriver`. This divergence is intentional and documented.

### Known limitations

- `OpenSpoutDriver::listSheets` returns zeroed row and column counts. See
  `docs/drivers.md`.
- The benchmark command requires `openspout/openspout` even when the configured
  reader driver is `maatwebsite`. It uses OpenSpout to write the fixture.
- `memory_reset_peak_usage()` used by `BenchmarkCommand` requires PHP 8.2+.
  Peak memory reported on PHP 8.1 includes framework bootstrap overhead.

[1.0.0]: https://github.com/akbarjimi/excel-importer-pipeline/releases/tag/v1.0.0

## [v0.8.0] - 2025-09-06

### Added
- Introduced transformer and validator foundation allowing per-sheet and per-column callbacks.
- Implemented `ExcelRowError` model and persistence for rows failing validation or transformation.
- Enhanced `ProcessChunkJob` to handle errors and store them for later user correction.
- Updated chunk processing to be memory-friendly and idempotent.
- Added multi-language support for error messages.
- Improved event-driven architecture and job orchestration.

### Changed
- Replaced `PersistService` with `ExcelRowRepository` for row insertion and chunk index updates.
- Applied ENUMs for status fields across `ExcelFile`, `ExcelSheet`, `ExcelRow`, and `ExcelRowChunk`.
- Refactored `TransformService` and `ValidateService` for singleton-per-sheet usage in queue workers.
- Simplified transformer logic to avoid unnecessary JSON decoding (handled by model casts).

### Fixed
- Fixed test failures related to deterministic chunking and row indexing.
- Corrected idempotency issues in `ProcessChunkJob`.
- Resolved memory inefficiency in large-file processing.

---

## [v0.7.0] - 2025-08-28

### Added
- Event-driven architecture for Excel file, sheet, and row processing.
- `ChunkerService` for deterministic chunk creation.
- `ProcessChunkJob` to handle individual row chunks.
- Initial transformer and validator structure with callback support.
- Basic test coverage for chunking, row processing, and event dispatching.

### Changed
- Updated directory structure for services, repositories, and jobs to follow Laravel standards.

### Fixed
- Initial test adjustments for row indexing and unique constraints.

---

## [v0.6.0] - 2025-08-15

### Added
- `ExcelRowChunks` model and migration.
- Status tracking for file, sheet, row, and chunk levels.
- Queueable jobs for row chunk processing.
- Logging for chunk processing success and failure.
- Retry logic for failed jobs.

### Changed
- Refined migration schemas for `excel_rows` and `excel_sheets`.
- Introduced foreign key constraints for referential integrity.

### Fixed
- Corrected insertion logic for chunked rows to prevent overlaps.

## [v0.5.0] - 2025-07-27

### Added
- Reads and stores rows from the first discovered sheet immediately after metadata extraction.
- Introduced `insert_batch_size` config to control database batch inserts.
- Fires `RowsExtracted` event with inserted row count.
- Updated `HandleSheetsDiscovered` to trigger `RowExtractionService`.

## [v0.4.0] - 2025-07-21

### Added
- Auto discovery and DB persistence of Excel sheet metadata
- `HandleExcelUploaded` listener
- `SheetDiscoveryService` and `ExcelSheetRepository`
- Sheet metadata test coverage
