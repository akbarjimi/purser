# Console Commands

Three artisan commands ship with the package.

## `excel:status`

    php artisan excel:status {fileId}

Prints the full state of an import: file metadata, per-sheet status, chunk
counts by status, row counts by status, and total error count.

    File #42
    +----------------+---------------------+
    | Field          | Value               |
    +----------------+---------------------+
    | Name           | users.xlsx          |
    | Status         | completed           |
    | Batch ID       | 9c8e...             |
    | Error          | —                   |
    | Trashed        | no                  |
    | Created        | 2026-09-21 10:14:02 |
    | Rows Extracted | 2026-09-21 10:14:05 |
    | Completed      | 2026-09-21 10:14:19 |
    +----------------+---------------------+

    Sheets
    +----+--------+-----+-------------------+-------+--------+------+
    | ID | Name   | Idx | Status            | Rows  | Chunks | Done |
    +----+--------+-----+-------------------+-------+--------+------+
    | 7  | Users  | 0   | completed         | 5000  | 5      | 0    |
    | 8  | Orders | 1   | chunks_dispatched | 12000 | 12     | 0    |
    +----+--------+-----+-------------------+-------+--------+------+

    Chunks
    +-------------+-------+
    | Status      | Count |
    +-------------+-------+
    | pending     | 0     |
    | processing  | 0     |
    | completed   | 17    |
    | failed      | 0     |
    +-------------+-------+

    Rows
    +-------------------+-------+
    | Status            | Count |
    +-------------------+-------+
    | pending           | 0     |
    | validated         | 16850 |
    | failed_validation | 148   |
    | processed         | 0     |
    | failed            | 2     |
    +-------------------+-------+

    Errors: 152

Exit codes:

- `0` — file found, status printed.
- `1` — file not found.

Soft-deleted files are still printed. The `Trashed` row reads `yes`.

## `excel:retry`

    php artisan excel:retry {fileId}

Re-dispatches failed chunks for a file. See `docs/error-recovery.md` for the
full workflow.

Preconditions, all enforced:

1. The file exists and is not soft-deleted.
2. The file's status is `FAILED`.
3. At least one chunk has status `FAILED`.

If any precondition fails, the command prints the reason and exits with code
`1`.

On success:

    Reset 3 chunks. Dispatched 3 retry jobs for file 42.

The file transitions to `PROCESSING`, the failed chunks are reset to
`PENDING`, and a `Bus::batch` named `excel-retry:42` is dispatched. When the
batch completes, the file is marked `COMPLETED` and `FileProcessingCompleted`
is fired. If any chunk still fails, the file is marked `FAILED` with a count.

Example failure output:

    File [42] status is [completed]. Retry requires [failed].

## `excel:benchmark`

    php artisan excel:benchmark {--rows=10000} {--driver=} {--disk=local} {--keep}

Generates a synthetic `.xlsx` fixture, runs it through the full pipeline, and
prints phase timings and memory usage. Used to compare drivers or tune
`chunk_size`.

### Options

| Option     | Default        | Meaning                                            |
|------------|----------------|----------------------------------------------------|
| `--rows`   | `10000`        | Number of data rows in the generated fixture       |
| `--driver` | config default | Reader driver to use for this run                  |
| `--disk`   | `local`        | Storage disk for the fixture; must be a local disk |
| `--keep`   | false          | Skip cleanup of the fixture and the file row       |

### Output

    Benchmark: 10000 rows

    +------------+----------+--------------+
    | Phase      | Duration | Throughput   |
    +------------+----------+--------------+
    | Extraction | 1.412s   | 7,082 rows/s |
    | Processing | 4.938s   | 2,025 rows/s |
    | Handler    | 0.011s   | 909,090 rows/s |
    | Total      | 6.361s   | 1,572 rows/s |
    +------------+----------+--------------+

    +-------------------+--------------+
    | Metric            | Value        |
    +-------------------+--------------+
    | Peak memory       | 42.31 MB     |
    | Rows persisted    | 10,000       |
    | Rows to handler   | 10,000       |
    | Reader driver     | maatwebsite  |
    | Chunk size        | 1000         |
    | Insert batch size | 100          |
    | PHP               | 8.2.33       |
    | Queue             | sync         |
    +-------------------+--------------+

The handler phase measures the time the `BenchmarkHandler` spends iterating
the validated rows. It is intentionally trivial — one increment per row — so
that the number reflects pipeline overhead, not handler cost.

### Requirements

- `openspout/openspout` is required to write the fixture, even if you are
  benchmarking the PhpSpreadsheet driver.
- The disk must be `local`. Remote disks are rejected because the pipeline
  needs a real path for the reader.
- `queue.default` is set to `sync` for the duration of the command, so the
  whole pipeline runs in one process.

### Caveats

- `memory_reset_peak_usage()` requires PHP 8.2 or newer. On PHP 8.1 the peak
  memory includes framework bootstrap.
- Do not run two benchmarks in the same process. The `--driver` option
  mutates `excel-importer.driver` and is not reset between runs.
- The benchmark measures wall-clock time on your machine. Numbers are not
  comparable across hardware.

## Exit codes

| Command           | Code | Meaning                                                  |
|-------------------|------|----------------------------------------------------------|
| `excel:status`    | `0`  | File printed                                             |
| `excel:status`    | `1`  | File not found                                           |
| `excel:retry`     | `0`  | Retry batch dispatched                                   |
| `excel:retry`     | `1`  | Precondition failed                                      |
| `excel:benchmark` | `0`  | Benchmark completed                                      |
| `excel:benchmark` | `1`  | Validation failed, missing dependency, or pipeline error |