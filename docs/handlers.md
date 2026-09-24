# Writing a Handler

A handler is the only thing you have to write. Everything else is
configuration.

## The contract

    <?php

    declare(strict_types=1);

    namespace Akbarjimi\Purser\Contracts;

    interface ImportHandler
    {
        /**
         * @param  int  $fileId  The ID of the imported file.
         * @param  iterable<ValidatedRow>  $rows  Stream of validated rows.
         */
        public function handle(int $fileId, iterable $rows): void;
    }

Two guarantees:

- Every row in `$rows` has passed validation for its sheet.
- The iterable is lazy. Rows are streamed from the database, not loaded into
  memory. A handler that touches a single row in a ten-million-row file uses
  memory for one row.

## ValidatedRow

    final class ValidatedRow
    {
        public function __construct(
            public readonly int $rowIndex,
            public readonly array $data,
        ) {}
    }

`rowIndex` is the 0-based position of the row in the source sheet, so operators
can find it in the original spreadsheet. `data` is the mapped and transformed
payload — the keys are the target field names from your mapping config.

## Dispatch

    use Akbarjimi\Purser\Services\ImportManager;

    app(ImportManager::class)
        ->import('uploads/users.xlsx', disk: 's3')
        ->withHandler(UserImportHandler::class)
        ->dispatch();

The handler class is resolved through the container. Anything you can inject
into a controller, you can inject into a handler:

    final class UserImportHandler implements ImportHandler
    {
        public function __construct(
            private readonly UserRepository $users,
            private readonly AuditLog $audit,
        ) {}

        public function handle(int $fileId, iterable $rows): void
        {
            // ...
        }
    }

## The stream contract

The iterable is a `LazyCollection`. Do not call `->all()`, `->toArray()`, or
`iterator_to_array()` on it. Iterate once.

If you need to iterate twice, copy to your own storage on the first pass.

## Transaction patterns

Wrap the whole handler in a transaction if you want the import to be all or
nothing:

    public function handle(int $fileId, iterable $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                User::create($row->data);
            }
        });
    }

Do not do this on files above a few thousand rows. A long-running transaction
holds row locks, blocks concurrent writes, and rolls back everything if the
last row fails. Prefer chunked transactions:

    public function handle(int $fileId, iterable $rows): void
    {
        $buffer = [];

        foreach ($rows as $row) {
            $buffer[] = $row->data;

            if (count($buffer) === 500) {
                DB::transaction(fn () => User::insert($buffer));
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::transaction(fn () => User::insert($buffer));
        }
    }

## Idempotency

If your handler is not idempotent, a retried file will produce duplicates.
Two options:

**Option A. Upsert by natural key.**

    foreach ($rows as $row) {
        User::updateOrCreate(
            ['email' => $row->data['email']],
            ['name' => $row->data['name']],
        );
    }

**Option B. Check the file meta.**

    public function handle(int $fileId, iterable $rows): void
    {
        $file = ExcelFile::find($fileId);

        if ($file->meta['imported_at'] ?? null) {
            return;
        }

        foreach ($rows as $row) {
            User::create($row->data);
        }

        $file->update([
            'meta' => array_merge($file->meta, ['imported_at' => now()->toIso8601String()]),
        ]);
    }

Pick upsert-by-key unless the domain forbids it.

## Accessing the file record

The handler receives the file ID, not the model. If you need the file's
metadata:

    use Akbarjimi\Purser\Models\ExcelFile;

    $file = ExcelFile::with('excelSheets')->find($fileId);

    $file->file_name;         // original filename
    $file->disk;              // storage disk
    $file->path;              // path on that disk
    $file->meta['uploaded_by'] ?? null;

## Common handler shapes

### Insert every row into a single table

    public function handle(int $fileId, iterable $rows): void
    {
        foreach ($rows as $row) {
            Order::create([
                'reference' => $row->data['ref'],
                'total' => $row->data['total'],
                'placed_at' => $row->data['date'],
            ]);
        }
    }

### Group rows by a foreign key

    public function handle(int $fileId, iterable $rows): void
    {
        foreach ($rows as $row) {
            $order = Order::firstOrCreate(['reference' => $row->data['order_ref']]);

            $order->lines()->create([
                'sku' => $row->data['sku'],
                'qty' => $row->data['qty'],
            ]);
        }
    }

### Call an external API

    public function handle(int $fileId, iterable $rows): void
    {
        foreach ($rows as $row) {
            Http::post('https://api.example.com/users', $row->data);
        }
    }

For high-volume API calls, dispatch sub-jobs rather than calling synchronously
inside the handler. The handler runs on the queue already, but a single slow
call blocks every remaining row.

## What not to do

- Do not call `dispatch()` from inside a handler. The pipeline is already
  complete at this point. If you need downstream work, dispatch sub-jobs.
- Do not modify `excel_rows`, `excel_row_chunks`, or `excel_row_errors` from a
  handler. Those are owned by the pipeline.
- Do not assume the handler runs synchronously with `dispatch()`. On a real
  queue, it runs on a worker, possibly hours later.