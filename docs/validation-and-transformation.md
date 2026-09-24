# Validation and Transformation

Two optional stages run between the reader and the handler. Both are
configured per sheet in `config/purser-sheets.php`.

The order is fixed:

    raw row (column letters)
      -> mapping
      -> transformer
      -> validation
      -> ValidatedRow

Mapping is not a class. It is a config array. Transformation and validation
are contracts you implement or configure.

## Mapping

    'mapping' => [
        'name'  => 'A',
        'email' => 'B',
        'age'   => 'C',
    ],

Keys are the field names your handler will receive. Values are column letters
as returned by the reader driver.

`TransformService::applyMapping()` walks the mapping and builds a new array
with the target keys. If a source column is absent, the target key is `null`.
No error is raised.

If `mapping` is omitted entirely, the raw row is passed through, filtered to
drop any key that starts with `_`. This is how error report columns are
excluded. Exploratory imports can rely on this; production imports should
always map explicitly.

## Validation

    'validation' => [
        'name'  => 'required|string|max:255',
        'email' => 'required|email',
        'age'   => 'required|integer|min:18',
    ],

Standard Laravel rules. Values may be a string (`'required|email'`) or an array
(`['required', 'email']`). They are passed directly to `Validator::make()`.

Validation runs against the output of the transformer, not the raw row. If you
have no transformer, it runs against the mapped row.

`ValidateService::apply()` returns an empty array on success, or an array of
`field => [messages]` on failure.

### No rules configured

If a sheet has no `validation` key and `purser.strict_validation` is
`false`, every row passes. If `strict_validation` is `true`, a
`RuntimeException` is thrown naming the sheet. Set strict mode in production to
catch misconfiguration.

### Where failures land

A row that fails validation is marked `ExcelRowStatus::FAILED_VALIDATION`. One
`ExcelRowError` is written per rule violation with `error_type: validation`.

The row is not retried, not passed to the handler, and not re-validated. The
chunk continues with the next row.

Retrieve failures through `ErrorReportService`. See `docs/error-recovery.md`.

## Transformation

Implement `TransformerInterface` and reference it by class-string in the sheet
config.

    <?php

    declare(strict_types=1);

    namespace App\Transformers;

    use Akbarjimi\Purser\Contracts\TransformerInterface;
    use Akbarjimi\Purser\Models\ExcelSheet;

    final class UserTransformer implements TransformerInterface
    {
        public function transform(array $mappedRow, ExcelSheet $sheet): array
        {
            return [
                'name'  => trim((string) $mappedRow['name']),
                'email' => strtolower(trim((string) $mappedRow['email'])),
                'age'   => (int) $mappedRow['age'],
            ];
        }
    }

    'transformer' => App\Transformers\UserTransformer::class,

The transformer receives the row after mapping. Its return value is what
validation sees and what the handler ultimately receives.

### Container resolution

The transformer is instantiated through the container. Constructor injection
works:

    final class UserTransformer implements TransformerInterface
    {
        public function __construct(
            private readonly CountryResolver $countries,
        ) {}

        public function transform(array $mappedRow, ExcelSheet $sheet): array
        {
            // ...
        }
    }

### Failures

If the transformer throws, the row is marked `ExcelRowStatus::FAILED`, a
single `ExcelRowError` is written with `error_type: system`, and the chunk
continues. The exception message is the error message.

Do not swallow exceptions inside a transformer. Let them propagate.

### What not to do in a transformer

- Do not touch the database for each row. A transformer runs once per row and
  a single slow query multiplies by the file size. Load lookup tables once, in
  the constructor, and pass them into the transform call. If the table is
  large, use a caching layer.
- Do not dispatch jobs. The pipeline owns the queue.
- Do not write to `excel_rows` or `excel_row_errors`. Those belong to the
  pipeline.

## Putting it together

Given this config:

    'Users' => [
        'mapping' => [
            'name'  => 'A',
            'email' => 'B',
            'age'   => 'C',
        ],
        'transformer' => App\Transformers\UserTransformer::class,
        'validation' => [
            'name'  => 'required|string|max:255',
            'email' => 'required|email',
            'age'   => 'required|integer|min:18',
        ],
    ],

And this raw row from the reader:

    ['A' => '  Alice  ', 'B' => 'ALICE@EXAMPLE.COM', 'C' => '30']

Mapping produces:

    ['name' => '  Alice  ', 'email' => 'ALICE@EXAMPLE.COM', 'age' => '30']

The transformer produces:

    ['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30]

Validation passes. The handler receives a `ValidatedRow` with that data.

If the row had been `['A' => '', 'B' => 'not-an-email', 'C' => '15']`, the
mapping and transformer would run, validation would fail on all three fields,
and three `ExcelRowError` rows would be written. The handler would not see this
row.

## Contract summary

| Interface              | Method                                                  | Input           | Output                                            |
|------------------------|---------------------------------------------------------|-----------------|---------------------------------------------------|
| `TransformerInterface` | `transform(array $mappedRow, ExcelSheet $sheet): array` | Mapped row      | Row to validate                                   |
| `ValidatorInterface`   | `apply(array $payload, ExcelSheet $sheet): array`       | Transformed row | `[]` on success, `field => [messages]` on failure |

`ValidatorInterface` is implemented by `ValidateService`. You do not need to
implement it unless you want to replace Laravel's validator entirely. If you
do, register your implementation by binding it in a service provider that runs
after `ExcelImporterServiceProvider`.

## Overriding the validator

The package binds `ValidateService` as a concrete class, not through an
interface binding. To replace it, override the binding after the package's
provider registers:

    // AppServiceProvider::register()
    $this->app->bind(ValidateService::class, MyValidator::class);

Do this only if you need validation rules that Laravel's validator cannot
express. Laravel's rules cover almost everything.