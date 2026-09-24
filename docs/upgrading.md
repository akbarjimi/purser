# Upgrading

## From v0.x to v1.0.0

The API is additive. If you are on v0.8.0 or later, the migration is
mechanical. If you are on a version before v0.8.0, upgrade to v0.8.0 first,
then read this page.

### Breaking changes

None. The v1.0.0 release is the first stable release; the public API is frozen
at this version. Everything below is a fix or a clarification that changes
behaviour, not signatures.

### Behavioural fixes

**Chunk processing no longer wraps the row loop in a single transaction.**

Before: `ChunkProcessor::process()` opened `DB::beginTransaction()` before the
row loop. If a later `bulkUpdate` threw, `DB::rollBack()` discarded row errors
that had been written for rows earlier in the chunk. The audit trail was lost.

After: the transaction is removed. Each row's error write and each row's status
update commit independently. A failure of a later `bulkUpdate` leaves earlier
errors intact and marks the chunk `FAILED`.

What this means for you: if you were relying on chunk-level atomicity to
prevent partial row updates, this release does not provide it. Wrap your
handler in a transaction if you need that.

**Missing sheets now fail the chunk instead of fataling.**

Before: a hard-deleted sheet caused a fatal `Error` on
`$sheet->excelFile->trashed()`. The chunk was never marked failed. The job
retried three times and died.

After: `ChunkProcessor::process()` checks for a null sheet and marks the chunk
`FAILED` with the message `Sheet not found.`

What this means for you: chunks that used to consume three retries and then
fail silently now fail on the first attempt with a diagnostic. Nothing to
change in your code.

**OpenSpout driver throws on missing dependency.**

Before: `new Reader` in `OpenSpoutDriver::readRows()` threw
`Error: Class "OpenSpout\Reader\XLSX\Reader" not found` when
`openspout/openspout` was not installed.

After: both entry points check `class_exists()` and throw
`MissingDriverDependencyException::for('openspout', 'openspout/openspout')`,
which names the composer command to run.

What this means for you: catch `MissingDriverDependencyException` if you were
catching `Error` before. You almost certainly were not. Nothing to change.

### Documentation clarification

**`OpenSpoutDriver::listSheets` returns zeroed row and column counts.**

This is not a change — the code has always returned `0` for `totalRows` and
`totalColumns`. It is now documented in `docs/drivers.md`. If you display row
counts before extraction, either switch to `PhpSpreadsheetDriver` or accept
that the number is not available.

### Migration steps

1. Update `composer.json`:

       composer require akbarjimi/purser:^1.0

2. Run the test suite in your application:

       php artisan test

3. If your tests touch chunk processing directly, verify they still pass. The
   two behavioural fixes listed above are the only places where observable
   behaviour changes.

4. If you have custom code that reads the transaction state during chunk
   processing, review it. The outer transaction no longer exists.

5. No database migrations are required. The schema is unchanged since v0.8.0.

## From v0.8.0 to v0.9.x

v0.9.x was a pre-release line. If you are on it, the upgrade to v1.0.0 is the
same as above. There are no intermediate steps.

## From v0.7.x or earlier

The package was restructured between v0.7 and v0.8. The following classes
were renamed:

| v0.7 and earlier           | v0.8+                           |
|----------------------------|---------------------------------|
| `Import\FileImporter`      | `Services\ImportManager`        |
| `Import\PendingFileImport` | `Services\PendingImport`        |
| `Import\SheetExtractor`    | `Services\RowExtractionService` |
| `Import\ChunkBuilder`      | `Services\ChunkerService`       |

If your application referenced any of the old names, update the imports. The
`ExcelReaderDriver` and `ImportHandler` contracts are unchanged from v0.7.

## Future upgrades

Starting with v1.0.0, this package follows semantic versioning:

- **Patch releases** (`1.0.x`) contain bug fixes only. No public API changes.
- **Minor releases** (`1.x.0`) add features. No breaking changes.
- **Major releases** (`x.0.0`) may break the public API. Every breaking change
  is documented here under a new section.

The public API consists of:

- All classes in `Akbarjimi\Purser\Contracts`
- All classes in `Akbarjimi\Purser\DTOs`
- All classes in `Akbarjimi\Purser\Enums`
- Public methods on `Services\ImportManager`, `Services\PendingImport`,
  `Services\ErrorReportService`
- The `purser` and `purser-sheets` config keys
- The three artisan command signatures

Everything else — repositories, listeners, jobs, models — is internal and may
change without notice. If you depend on an internal class, prefer a listener
on one of the four public events instead.