# Usage Guide

## Install
```bash
composer require akbarjimi/purser
```

## Publish config (if needed later)
```bash
php artisan vendor:publish --tag=purser-config
```

## Example Controller
```php
public function import(Request \$request, ExcelImportService \$importer)
{
    \$request->validate(['file' => 'required|file|mimes:xlsx']);
    \$path = \$request->file('file')->store('imports');

    \$importer->import(storage_path("app/\$path"));

    return response()->json(['status' => 'queued']);
}
```
