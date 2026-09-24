# Configuration Reference

Two files are published to `config/`:

- `purser.php` — pipeline-wide settings
- `purser-sheets.php` — per-sheet mapping, transformation, validation

Publish with:

```php
    php artisan vendor:publish --tag=purser
    php artisan vendor:publish --tag=purser-sheets
```

## `purser.php`

### `driver`

```php
    'driver' => env('PURSER_DRIVER', 'maatwebsite'),
```

Which reader driver to use. `maatwebsite` selects `PhpSpreadsheetDriver`;
`openspout` selects `OpenSpoutDriver`. Both must be resolvable through the
container. If the underlying package is not installed, the driver throws
`MissingDriverDependencyException` at first use.

### `chunk_size`

```php
    'chunk_size' => env('PURSER_CHUNK_SIZE', 1000),
```

Number of rows per processing chunk. Lower values reduce memory per worker
and increase the number of queued jobs. Higher values reduce job overhead but
each job holds more of the sheet in memory at once.

For SQLite or other single-writer databases, keep this under 5,000. For
Postgres or MySQL with a fast disk, 5,000 to 20,000 is safe.

### `insert_batch_size`

```php
    'insert_batch_size' => env('PURSER_INSERT_BATCH_SIZE', 100),
```

Rows per database write during extraction and during chunk processing's
`bulkUpdate` calls. This is the size of a single `INSERT` statement, not the
size of a transaction. The pipeline does not wrap the whole chunk in a
transaction.

### `default_disk`

```php
    'default_disk' => env('PURSER_DISK', 'local'),
```

Storage disk used when `ImportManager::import()` is called without an
explicit disk. Falls back to `filesystems.default` if this is null.

### `queue`

```php
    'queue' => env('PURSER_QUEUE', 'default'),
```

Queue connection name for all jobs and batches dispatched by the package. Set
this to a queue with a real worker backing it in production. `sync` works for
tests and single-request imports.

### `max_sheets`

```php
    'max_sheets' => 50,
```

Hard cap on the number of sheets in a single file. Files exceeding this are
marked `FAILED` with a descriptive error before any rows are extracted. Set
higher if you legitimately import workbooks with many sheets.

### `strict_validation`

```php
    'strict_validation' => env('PURSER_STRICT_VALIDATION', false),
```

If `true`, `ValidateService` throws when a sheet has no validation rules
configured. If `false`, sheets without rules pass every row.

Leave at `false` when you are building a first import and want to iterate. Set
to `true` in production to catch misconfiguration where a rule set was
forgotten.

### `hash_algo`

```php
    'hash_algo' => env('PURSER_HASH_ALGO', 'sha256'),
```

Algorithm used to compute `content_hash` for each row. The hash, combined with
`excel_sheet_id`, is a unique key on `excel_rows`. Re-extracting the same file
produces the same hashes and upserts the same rows instead of duplicating.

Options: any hash algorithm supported by `hash()`. `sha256` is the default.
`md5` is faster. `xxh128` is fastest if your PHP build has ext-xxhash.

Changing this after importing files with an older algorithm is not supported —
you would need to re-extract.

### `advanced.bulk_upsert_chunk_size`

```php
    'advanced' => [
        'bulk_upsert_chunk_size' => env('PURSER_BULK_UPSERT_CHUNK_SIZE', 500),
    ],
```

Second-level chunking inside `ExcelRowRepository::bulkUpsert()` and
`bulkUpdate()`. Rows are written in slices of this size within each call. Do
not change this unless you are troubleshooting a specific database limit;
leave it at the default.

### `logging.enabled`

```php
    'logging' => [
        'enabled' => (bool) env('PURSER_LOG_ENABLED', true),
        'channels' => ['stack'],
        'level' => 'info',
    ],
```

`enabled` is read by consumers if they want to gate logging on their own.
The package itself always writes through `LogsImportActivity`, which uses
`Log::stack()` with the channels below.

### `logging.channels`

```php
    'channels' => ['stack'],
```

Array of log channels. Passed to `Log::stack()`. Set to `['daily']` to
isolate importer logs from the rest of the application, or
`['stack', 'importer']` to dual-write.

### `logging.level`

```php
    'level' => 'info',
```

Minimum level written by the package's internal `importLog()` calls. Not
currently consumed by the trait; reserved for future use.

### `drivers`

```php
    'drivers' => [
        'maatwebsite' => PhpSpreadsheetDriver::class,
        'openspout'   => OpenSpoutDriver::class,
    ],
```

Map of driver keys to reader driver classes. Registered users can add custom
drivers here and select them via the `driver` key. Each class must implement
`ExcelReaderDriver` and accept no required constructor arguments (it is
instantiated by the container).

## `purser-sheets.php`

Keyed by sheet name. The sheet name is matched against the `name` column of
`excel_sheets`, which comes directly from the file.

### `mapping`

```php
    'mapping' => [
        'name'  => 'A',
        'email' => 'B',
        'age'   => 'C',
    ],
```

Maps target field names to source column letters. The keys become the array
keys in `ValidatedRow::$data`. The values are the column letters as returned
by the reader driver.

If a mapping is omitted entirely, the raw row is passed through keyed by
column letter — useful for exploratory imports but not recommended for
production.

If a source column is absent from the file, the mapped field is `null`.

### `transformer`

```php
    'transformer' => App\Transformers\UserTransformer::class,
```

Optional. A class implementing `TransformerInterface`. It receives the mapped
row after `mapping` is applied and returns the array that will be validated.
Use it to coerce types, strip whitespace, combine fields, or enrich from
external sources.

The class is resolved through the container, so it can have its own
dependencies.

If a transformer throws, the row is marked `FAILED` with `error_type: system`
and the exception message is written to `excel_row_errors`.

### `validation`

```php
    'validation' => [
        'name'  => 'required|string|max:255',
        'email' => 'required|email',
        'age'   => 'required|integer|min:18',
    ],
```

Standard Laravel validation rules. Keys are field names from the mapping.
Values are rule strings or arrays of rules, passed directly to
`Validator::make()`.

Validation runs against the output of the transformer, not the raw row.

A row that fails validation is marked `FAILED_VALIDATION`. Each rule violation
becomes one `ExcelRowError`. The row does not reach the handler.

If validation is omitted and `purser.strict_validation` is `false`,
every row passes.

## Environment variables

Every key with an `env()` call in the config file can be set via `.env`:

```dotenv
    PURSER_DRIVER=openspout
    PURSER_CHUNK_SIZE=500
    PURSER_INSERT_BATCH_SIZE=200
    PURSER_DISK=s3
    PURSER_QUEUE=imports
    PURSER_HASH_ALGO=sha256
    PURSER_STRICT_VALIDATION=true
    PURSER_LOG_ENABLED=true
    PURSER_BULK_UPSERT_CHUNK_SIZE=500
```

`max_sheets` is not currently wired to an env variable. Edit the config file
directly if you need to change it.