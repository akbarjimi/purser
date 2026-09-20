# Contributing

Contributions are welcome. Read this before opening a PR.

## Setup

    git clone git@github.com:akbarjimi/excel-importer-pipeline.git
    cd excel-importer-pipeline
    composer install

## Run the tests

    composer test

Or directly:

    vendor/bin/pest

With coverage (requires PCOV):

    vendor/bin/pest --coverage --min=80 --ci

## Code style

Laravel Pint, `laravel` preset. Run before every commit:

    vendor/bin/pint

Do not commit formatting changes mixed with logic changes. One or the other.

## What we accept

- Bug fixes with a regression test that fails without the fix.
- New reader drivers that implement `ExcelReaderDriver`.
- Documentation fixes and clarifications.
- Performance improvements accompanied by benchmark output.

## What we decline

- New dependencies in `require`. The package depends only on `illuminate/*`.
  Anything else belongs behind a contract in `suggest`.
- Changes to the public API without a corresponding entry in `CHANGELOG.md`.
- Refactors of working code that do not fix a defect or reduce measurable
  complexity.
- Test-only PRs that increase coverage without adding behavioral assertions.

## Commit format

    <type>(<scope>): <imperative, lowercase, ≤ 72 chars>

Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `build`, `ci`, `perf`.

Examples:

    fix(processor): persist row errors outside chunk transaction
    feat(drivers): add csv reader driver
    docs: clarify error report structure

## Pull request checklist

- Tests pass locally on the PHP version in `.github/workflows/tests.yml`.
- `vendor/bin/pint --test` returns clean.
- `CHANGELOG.md` has an entry under `## [Unreleased]`.
- The PR description names the problem, not the solution. Explain what is
  broken or missing; the diff explains how you fixed it.

## Reporting bugs

Open an issue. Include:

- PHP version, Laravel version, and which driver you are using.
- The command or code that reproduces the failure.
- The full stack trace if an exception was thrown.
- The output of `php artisan excel:status {fileId}` if the failure is
  data-related.

Minimal reproducible examples get fixed. "It doesn't work" does not.