<?php

declare(strict_types=1);

namespace Akbarjimi\Purser\Console\Commands;

use Akbarjimi\Purser\Contracts\ImportHandler;
use Akbarjimi\Purser\Enums\ExcelFileStatus;
use Akbarjimi\Purser\Events\AllRowsExtracted;
use Akbarjimi\Purser\Events\FileProcessingCompleted;
use Akbarjimi\Purser\Events\FileSheetsScanCompleted;
use Akbarjimi\Purser\Models\ExcelFile;
use Akbarjimi\Purser\Services\ImportManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Throwable;

final class BenchmarkCommand extends Command
{
    protected $signature = 'excel:benchmark
                            {--rows=10000 : Number of data rows in the generated fixture}
                            {--driver= : Reader driver to use (defaults to config)}
                            {--disk=local : Local storage disk for the fixture}
                            {--keep : Do not delete the fixture or database records}';

    protected $description = 'Benchmark the Excel import pipeline against a synthetic fixture.';

    public function handle(ImportManager $imports, Dispatcher $events): int
    {
        if (! class_exists(Writer::class)) {
            $this->error('openspout/openspout is required for the benchmark fixture: composer require --dev openspout/openspout');

            return self::FAILURE;
        }

        $rows = (int) $this->option('rows');
        if ($rows < 1) {
            $this->error('--rows must be a positive integer.');

            return self::FAILURE;
        }

        $disk = (string) $this->option('disk');

        if (($driver = $this->option('driver')) !== null) {
            config(['purser.driver' => $driver]);
        }

        if (config("filesystems.disks.{$disk}.driver") !== 'local') {
            $this->error("Disk [{$disk}] is not a local disk. Benchmark requires the [local] driver.");

            return self::FAILURE;
        }

        config(['queue.default' => 'sync']);

        $relative = 'purser-benchmark/'.'bench-'.$rows.'-'.bin2hex(random_bytes(4)).'.xlsx';
        $absolute = Storage::disk($disk)->path($relative);

        $this->generateFixture($absolute, $rows);

        $timings = ['start' => microtime(true)];

        $events->listen(FileSheetsScanCompleted::class, function () use (&$timings): void {
            $timings['sheets_scanned'] ??= microtime(true);
        });
        $events->listen(AllRowsExtracted::class, function () use (&$timings): void {
            $timings['rows_extracted'] ??= microtime(true);
        });
        $events->listen(FileProcessingCompleted::class, function () use (&$timings): void {
            $timings['completed'] ??= microtime(true);
        });

        $handler = new BenchmarkHandler;
        app()->instance(BenchmarkHandler::class, $handler);

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        try {
            $file = $imports->import($relative, $disk)
                ->withHandler(BenchmarkHandler::class)
                ->dispatch();
        } catch (Throwable $e) {
            $this->error("Benchmark failed: {$e->getMessage()}");

            if (! $this->option('keep')) {
                @unlink($absolute);
            }

            return self::FAILURE;
        }

        $timings['end'] = microtime(true);
        $peakBytes = memory_get_peak_usage(true);

        $this->report($rows, $timings, $peakBytes, $handler->count, $file);

        if ($this->option('keep')) {
            $this->newLine();
            $this->line("Fixture: {$absolute}");
            $this->line("File ID: {$file->id}");
        } else {
            ExcelFile::whereKey($file->id)->forceDelete();
            @unlink($absolute);
        }

        return self::SUCCESS;
    }

    private function generateFixture(string $path, int $rows): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create directory [{$directory}].");
        }

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('Bench');
            $writer->addRow(Row::fromValues(['id', 'name', 'email', 'age']));

            for ($i = 1; $i <= $rows; $i++) {
                $writer->addRow(Row::fromValues([
                    $i,
                    "User {$i}",
                    "user{$i}@example.com",
                    20 + ($i % 50),
                ]));
            }
        } finally {
            $writer->close();
        }
    }

    /**
     * @param  array<string, float>  $timings
     */
    private function report(int $rows, array $timings, int $peakBytes, int $handled, ExcelFile $file): void
    {
        $start = $timings['start'];
        $sheetsScanned = $timings['sheets_scanned'] ?? $start;
        $rowsExtracted = $timings['rows_extracted'] ?? $sheetsScanned;
        $completed = $timings['completed'] ?? ($timings['end'] ?? $start);
        $end = $timings['end'];

        $extract = $rowsExtracted - $sheetsScanned;
        $process = $completed - $rowsExtracted;
        $handler = $end - $completed;
        $total = $end - $start;

        $this->newLine();
        $this->info("Benchmark: {$rows} rows");

        if ($file->status !== ExcelFileStatus::COMPLETED) {
            $this->warn("File finished with status [{$file->status->value}], expected [completed].");
        }

        $this->newLine();
        $this->table(
            ['Phase', 'Duration', 'Throughput'],
            [
                ['Extraction', $this->fmt($extract), $this->rate($rows, $extract)],
                ['Processing', $this->fmt($process), $this->rate($rows, $process)],
                ['Handler', $this->fmt($handler), $this->rate($handled, $handler)],
                ['Total', $this->fmt($total), $this->rate($rows, $total)],
            ],
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['Peak memory', $this->bytes($peakBytes)],
                ['Rows persisted', number_format($rows)],
                ['Rows to handler', number_format($handled)],
                ['Reader driver', (string) config('purser.driver')],
                ['Chunk size', (string) config('purser.chunk_size')],
                ['Insert batch size', (string) config('purser.insert_batch_size')],
                ['PHP', PHP_VERSION],
                ['Queue', 'sync'],
            ],
        );
    }

    private function fmt(float $seconds): string
    {
        return number_format($seconds, 3).'s';
    }

    private function rate(int $count, float $seconds): string
    {
        if ($seconds <= 0.0 || $count === 0) {
            return '—';
        }

        return number_format($count / $seconds, 0).' rows/s';
    }

    private function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $i = 0;

        while ($value >= 1024.0 && $i < count($units) - 1) {
            $value /= 1024.0;
            $i++;
        }

        return number_format($value, 2).' '.$units[$i];
    }
}

final class BenchmarkHandler implements ImportHandler
{
    public int $count = 0;

    public function handle(int $fileId, iterable $rows): void
    {
        foreach ($rows as $_) {
            $this->count++;
        }
    }
}
