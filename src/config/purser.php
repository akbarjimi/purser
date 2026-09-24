<?php

return [
    // ============================================================
    // Simple configuration – most users only need these
    // ============================================================

    /*
     * Number of rows per chunk for processing.
     * Lower = less memory, higher = faster.
     */
    'chunk_size' => env('EXCEL_IMPORTER_CHUNK_SIZE', 1000),

    /*
     * Number of rows inserted per database batch.
     * Lower = less memory, higher = faster.
     */
    'insert_batch_size' => env('EXCEL_IMPORTER_INSERT_BATCH_SIZE', 100),

    /*
     * Storage disk for Excel files.
     */
    'default_disk' => env('EXCEL_IMPORTER_DISK', 'local'),

    /*
     * Queue connection for background jobs.
     */
    'queue' => env('EXCEL_IMPORTER_QUEUE', 'default'),

    /*
     * Maximum number of sheets allowed per file.
     * Prevents abuse/memory issues.
     */
    'max_sheets' => 50,

    /*
     * If true, validation errors will throw exceptions.
     * If false, invalid rows are logged but import continues.
     */
    'strict_validation' => env('EXCEL_IMPORTER_STRICT_VALIDATION', false),

    /*
     * Excel reader driver: 'maatwebsite' (PhpSpreadsheet) or 'openspout'
     */
    'driver' => env('EXCEL_IMPORTER_DRIVER', 'maatwebsite'),

    // ============================================================
    // Advanced configuration – power users only
    // ============================================================

    /*
     * Hashing algorithm for row deduplication.
     * Options: md5, sha256, xxh128 (if available).
     */
    'hash_algo' => env('EXCEL_IMPORTER_HASH_ALGO', 'sha256'),

    /*
     * Bulk upsert chunk size for database operations.
     * Most users should leave this at 500.
     */
    'advanced' => [
        'bulk_upsert_chunk_size' => env('EXCEL_IMPORTER_BULK_UPSERT_CHUNK_SIZE', 500),
    ],

    /*
     * Logging configuration.
     */
    'logging' => [
        'enabled' => (bool) env('EXCEL_IMPORTER_LOG_ENABLED', true),
        'channels' => ['stack'],
        'level' => 'info',
    ],

    /*
     * Custom driver mappings (advanced).
     */
    'drivers' => [
        'maatwebsite' => \Akbarjimi\Purser\Drivers\PhpSpreadsheetDriver::class,
        'openspout' => \Akbarjimi\Purser\Drivers\OpenSpoutDriver::class,
    ],
];
